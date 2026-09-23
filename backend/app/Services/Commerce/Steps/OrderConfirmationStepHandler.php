<?php

namespace App\Services\Commerce\Steps;

use App\Jobs\SendOrderConfirmationMessage;
use App\Models\CommerceSetting;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Payment;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\Payments\PaymentDriverResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderConfirmationStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private PaymentDriverResolver $resolver) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        return $this->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $cart = new CartContext($session);

        if ($cart->isEmpty()) {
            $this->sender->send($session->conversation, 'Your cart is empty.');

            return app(WelcomeStepHandler::class)->enter($session);
        }

        [$order, $payment] = DB::transaction(function () use ($session, $cart) {
            $order = $this->createOrder($session, $cart);
            $this->createOrderItems($order, $cart);
            $payment = $this->createPayment($order);

            $session->update([
                'status' => 'completed',
                'step' => 'completed',
                'order_id' => $order->id,
                'context' => $cart->toArray(),
                'last_interaction_at' => now(),
            ]);

            return [$order, $payment];
        });

        $this->sender->send(
            $session->conversation,
            "Your order #{$order->order_number} has been placed! Total: {$order->grand_total}. We'll notify you once it's confirmed."
        );

        $this->initiatePayment($order, $payment);

        SendOrderConfirmationMessage::dispatch($order->id);

        return true;
    }

    private function createPayment(Order $order): Payment
    {
        return Payment::create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'method' => $order->payment_method,
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);
    }

    /**
     * Gateway payment methods (Razorpay/WhatsApp Pay) need a provider-side
     * initiate call to actually send the payment link/message -- COD/UPI's
     * initiate() is a no-op synthetic reference. Never let a gateway error
     * (e.g. a transient Razorpay/Graph API failure) block order placement,
     * since the order and Payment row are already committed by this point.
     */
    private function initiatePayment(Order $order, Payment $payment): void
    {
        if (! str_starts_with($order->payment_method, 'gateway:')) {
            return;
        }

        try {
            $this->resolver->forMethod($order->payment_method)->initiate($payment);
        } catch (\Throwable $e) {
            Log::error('OrderConfirmationStepHandler: payment initiation failed', [
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createOrder(OrderSession $session, CartContext $cart): Order
    {
        $fulfillment = $cart->fulfillment() ?? ['type' => 'pickup', 'delivery_charge' => 0];
        $subtotal = $cart->total();
        $deliveryCharge = $fulfillment['delivery_charge'] ?? 0;
        $grandTotal = $subtotal + $deliveryCharge;

        return Order::create([
            'company_id' => $session->company_id,
            'branch_id' => $session->branch_id,
            'contact_id' => $session->contact_id,
            'conversation_id' => $session->conversation_id,
            'order_number' => $this->nextOrderNumber($session->company_id),
            'status' => 'pending',
            'fulfillment_type' => $fulfillment['type'],
            'delivery_address' => $cart->customer('delivery_address'),
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'delivery_charge' => $deliveryCharge,
            'discount_total' => 0,
            'grand_total' => $grandTotal,
            'currency' => $session->branch?->currency ?? 'INR',
            'payment_method' => $cart->paymentMethod() ?? 'cod',
            'payment_status' => 'pending',
            'notes' => null,
            'placed_at' => now(),
        ]);
    }

    private function createOrderItems(Order $order, CartContext $cart): void
    {
        foreach ($cart->cart() as $line) {
            OrderItem::create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'product_variant_id' => $line['product_variant_id'],
                'name_snapshot' => $line['name'],
                'unit_price_snapshot' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'selected_options' => ['addons' => $line['addons'] ?? []],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    private function nextOrderNumber(int $companyId): string
    {
        $prefix = CommerceSetting::query()->where('company_id', $companyId)->value('order_number_prefix') ?? 'ORD';

        return $prefix.'-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
    }
}
