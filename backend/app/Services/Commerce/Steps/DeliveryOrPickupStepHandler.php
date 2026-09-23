<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\DeliveryPricingService;

class DeliveryOrPickupStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private DeliveryPricingService $pricing) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $cart = new CartContext($session);
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if ($cart->fulfillment() && $cart->fulfillment()['type'] === 'delivery' && ! $cart->customer('delivery_address')) {
            return $this->captureAddress($session, $cart, $inboundMessage);
        }

        if ($selection === 'fulfillment:pickup') {
            $cart->setFulfillment('pickup', 0);
            $cart->persist('payment_method');
            $this->sender->send($session->conversation, "Got it, we'll have your order ready for pickup.");

            return app(PaymentMethodStepHandler::class)->enter($session);
        }

        if ($selection === 'fulfillment:delivery') {
            $branch = $session->branch;
            $price = $branch ? $this->pricing->priceFor($branch, $cart->total()) : ['delivery_charge' => 0];
            $cart->setFulfillment('delivery', $price['delivery_charge']);
            $cart->persist('delivery_or_pickup');
            $this->sender->send($session->conversation, 'Please share your delivery location, or type your address.');

            return true;
        }

        return $this->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $cart = new CartContext($session);
        $cart->persist('delivery_or_pickup');

        $buttons = [
            ['id' => 'fulfillment:delivery', 'label' => 'Delivery'],
            ['id' => 'fulfillment:pickup', 'label' => 'Pickup'],
        ];
        $this->sender->send($session->conversation, 'Would you like delivery or pickup?', $buttons);

        return true;
    }

    private function captureAddress(OrderSession $session, CartContext $cart, Message $inboundMessage): bool
    {
        if ($inboundMessage->location_lat !== null && $inboundMessage->location_lng !== null) {
            $lat = (float) $inboundMessage->location_lat;
            $lng = (float) $inboundMessage->location_lng;

            $cart->setDeliveryCoordinates($lat, $lng);
            $cart->setCustomer('delivery_address', trim($inboundMessage->text ?? '') ?: 'Shared location');

            $branch = $session->branch;
            if ($branch) {
                $price = $this->pricing->priceFor($branch, $cart->total(), $lat, $lng);
                $cart->setFulfillment('delivery', $price['delivery_charge']);
                $cart->setDeliveryCoordinates($lat, $lng);
            }

            $cart->persist('payment_method');
            $this->sender->send($session->conversation, 'Thanks! Delivery location saved.');

            return app(PaymentMethodStepHandler::class)->enter($session);
        }

        $text = trim($inboundMessage->text ?? '');

        if ($text === '') {
            $this->sender->send($session->conversation, 'Please share your location, or send your delivery address as text.');

            return true;
        }

        $cart->setCustomer('delivery_address', $text);
        $cart->persist('payment_method');
        $this->sender->send($session->conversation, 'Thanks! Address saved.');

        return app(PaymentMethodStepHandler::class)->enter($session);
    }
}
