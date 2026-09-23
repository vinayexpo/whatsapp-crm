<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

class CartReviewStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if ($selection === 'cart:checkout') {
            return $this->beginCheckout($session);
        }

        if ($selection === 'cart:browse') {
            return app(WelcomeStepHandler::class)->enter($session);
        }

        if (str_starts_with($selection, 'cart:remove:')) {
            $lineId = substr($selection, strlen('cart:remove:'));
            $cart = new CartContext($session);
            $cart->removeItem($lineId);
            $cart->persist('cart_review');

            return $this->enter($session);
        }

        return $this->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $cart = new CartContext($session);

        if ($cart->isEmpty()) {
            $cart->persist('cart_review');
            $this->sender->send($session->conversation, 'Your cart is empty. Let\'s find something for you.');

            return app(WelcomeStepHandler::class)->enter($session);
        }

        $cart->persist('cart_review');

        $lines = collect($cart->cart())->map(function (array $line) {
            $addonsText = collect($line['addons'] ?? [])
                ->map(fn ($a) => " + {$a['name']}")
                ->implode('');

            return "{$line['quantity']}x {$line['name']}{$addonsText} — {$line['line_total']}";
        })->implode("\n");

        $buttons = [
            ['id' => 'cart:checkout', 'label' => 'Checkout'],
            ['id' => 'cart:browse', 'label' => 'Add more items'],
        ];

        $this->sender->send(
            $session->conversation,
            "Your cart:\n{$lines}\n\nTotal: {$cart->total()}",
            $buttons
        );

        return true;
    }

    private function beginCheckout(OrderSession $session): bool
    {
        $cart = new CartContext($session);

        if ($cart->isEmpty()) {
            return $this->enter($session);
        }

        $cart->persist('customer_details');
        $this->sender->send($session->conversation, 'Great! What name should we place this order under?');

        return true;
    }
}
