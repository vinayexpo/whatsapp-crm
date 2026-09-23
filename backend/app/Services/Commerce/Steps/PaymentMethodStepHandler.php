<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\Payments\PaymentDriverResolver;

class PaymentMethodStepHandler implements StepHandlerInterface
{
    private const LABELS = [
        'cod' => 'Cash on Delivery',
        'upi' => 'UPI (manual)',
        'gateway:razorpay' => 'Pay Online (Razorpay)',
        'gateway:whatsapp' => 'Pay with WhatsApp',
    ];

    public function __construct(private CommerceMessageSender $sender, private PaymentDriverResolver $resolver) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');
        $method = str_starts_with($selection, 'payment:') ? substr($selection, strlen('payment:')) : null;

        if (! $method || ! in_array($method, $this->resolver->availableMethods(), true)) {
            return $this->enter($session);
        }

        $cart = new CartContext($session);
        $cart->setPaymentMethod($method);
        $cart->persist('order_confirmation');

        return app(OrderConfirmationStepHandler::class)->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $cart = new CartContext($session);
        $cart->persist('payment_method');

        $methods = $this->resolver->availableMethods();

        $buttons = array_map(
            fn (string $method) => ['id' => 'payment:'.$method, 'label' => self::LABELS[$method] ?? $method],
            $methods,
        );

        $this->sender->send($session->conversation, 'How would you like to pay?', $buttons);

        return true;
    }
}
