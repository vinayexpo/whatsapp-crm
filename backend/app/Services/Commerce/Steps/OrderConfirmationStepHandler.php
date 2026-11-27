<?php

namespace App\Services\Commerce\Steps;

use App\Jobs\SendOrderConfirmationMessage;
use App\Models\CommerceSetting;
use App\Models\Inventory;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\CommerceMoney;
use App\Services\Commerce\Payments\PaymentDriverResolver;
use App\Services\Commerce\ProductPricingResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderConfirmationStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private PaymentDriverResolver $resolver) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        return $this->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $session->refresh();
        if ($session->order_id || $session->status !== 'active') {
            return true;
        }
        $cart = new CartContext($session);
        $minimum = $session->branch?->min_order_amount ?? 0;
        if ($cart->total() < $minimum) {
            $this->sender->send($session->conversation, 'The minimum order value is '.\App\Services\Commerce\CommerceMoney::format($minimum).'. Add more items before checkout.');
            return true;
        }

        if ($cart->isEmpty()) {
            $this->sender->send($session->conversation, 'Your cart is empty.');

            return app(WelcomeStepHandler::class)->enter($session);
        }

        try {
            [$order, $payment] = DB::transaction(function () use ($session, $cart) {
                $locked = OrderSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
                if ($locked->order_id) {
                    return [$locked->order, null];
                }
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
        } catch (ValidationException $e) {
            $this->sender->send($session->conversation, 'A product price or availability changed, or there is not enough stock. Send cancel to restart your order with the latest catalog.');

            return true;
        }

        if (! $payment) {
            return true;
        }

        $formattedTotal = CommerceMoney::format($order->grand_total, $order->currency);
        $this->sender->send(
            $session->conversation,
            "Your order #{$order->order_number} has been placed. Total: {$formattedTotal}. We'll notify you once it's confirmed."
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
            $this->resolver->forMethod($order->payment_method, $order->company_id)->initiate($payment);
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
        $taxTotal = 0;
        foreach ($cart->cart() as $line) {
            $product = Product::where('company_id', $session->company_id)->find($line['product_id']);
            $taxTotal += (int) round($line['line_total'] * ($product?->tax_rate_bp ?? 0) / 10000);
        }
        $grandTotal = $subtotal + $deliveryCharge + $taxTotal;

        return Order::create([
            'company_id' => $session->company_id,
            'branch_id' => $session->branch_id,
            'contact_id' => $session->contact_id,
            'conversation_id' => $session->conversation_id,
            'order_number' => $this->nextOrderNumber($session->company_id),
            'status' => 'pending',
            'fulfillment_type' => $fulfillment['type'],
            'delivery_address' => $cart->customer('delivery_address'),
            'delivery_lat' => $fulfillment['lat'] ?? null,
            'delivery_lng' => $fulfillment['lng'] ?? null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
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
            $product = Product::where('company_id', $order->company_id)->lockForUpdate()->find($line['product_id']);
            $variant = ! empty($line['product_variant_id']) ? $product?->variants()->find($line['product_variant_id']) : null;
            if (! $product || ! $product->is_active || $product->availability !== 'in_stock'
                || (int) $line['quantity'] < 1 || (int) $line['quantity'] > 99
                || app(ProductPricingResolver::class)->resolve($order->branch, $product, $variant) !== (int) $line['unit_price']) {
                throw ValidationException::withMessages(['cart' => 'Cart price or availability changed.']);
            }
            $stock = Inventory::where('company_id', $order->company_id)->where('branch_id', $order->branch_id)->where('product_id', $product->id)->where('product_variant_id', $line['product_variant_id'])->lockForUpdate()->first();
            $reserved = $stock && $stock->track_stock;
            if ($reserved) {
                if ($stock->stock_quantity < $line['quantity']) {
                    throw ValidationException::withMessages(['cart' => 'Insufficient inventory.']);
                }
                $stock->decrement('stock_quantity', $line['quantity']);
            }
            OrderItem::create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'product_variant_id' => $line['product_variant_id'],
                'name_snapshot' => $line['name'],
                'unit_price_snapshot' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'selected_options' => ['addons' => $line['addons'] ?? [], 'inventory_reserved' => (bool) $reserved],
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
