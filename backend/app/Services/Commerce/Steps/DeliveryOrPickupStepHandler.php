<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Models\Product;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\DeliveryPricingService;
use Illuminate\Support\Collection;

class DeliveryOrPickupStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private DeliveryPricingService $pricing) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $cart = new CartContext($session);
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        $awaitingAddress = $cart->fulfillment() && $cart->fulfillment()['type'] === 'delivery' && ! $cart->customer('delivery_address');

        if ($awaitingAddress && strtolower($selection) === 'pickup') {
            $cart->setFulfillment('pickup', 0);
            $cart->persist('payment_method');
            $this->sender->send($session->conversation, "Got it, we'll have your order ready for pickup.");

            return app(PaymentMethodStepHandler::class)->enter($session);
        }

        if ($awaitingAddress) {
            return $this->captureAddress($session, $cart, $inboundMessage);
        }

        if ($selection === 'fulfillment:pickup') {
            $unavailable = $this->itemsUnavailableFor($session, $cart, 'pickup_available');
            if ($unavailable->isNotEmpty()) {
                $this->sender->send($session->conversation, "Sorry, these items aren't available for pickup: {$unavailable->implode(', ')}. Please remove them from your cart or choose delivery.");

                return true;
            }

            $cart->setFulfillment('pickup', 0);
            $cart->persist('payment_method');
            $this->sender->send($session->conversation, "Got it, we'll have your order ready for pickup.");

            return app(PaymentMethodStepHandler::class)->enter($session);
        }

        if ($selection === 'fulfillment:delivery') {
            $unavailable = $this->itemsUnavailableFor($session, $cart, 'delivery_available');
            if ($unavailable->isNotEmpty()) {
                $this->sender->send($session->conversation, "Sorry, these items aren't available for delivery: {$unavailable->implode(', ')}. Please remove them from your cart or choose pickup.");

                return true;
            }

            $branch = $session->branch;
            $price = $branch ? $this->pricing->priceFor($branch, $cart->total()) : ['delivery_charge' => 0];
            $cart->setFulfillment('delivery', $price['delivery_charge']);
            $cart->persist('delivery_or_pickup');
            $this->sender->send($session->conversation, 'Please share your delivery location, or type your address.');

            return true;
        }

        return $this->enter($session);
    }

    /**
     * @return Collection<int, string>
     */
    private function itemsUnavailableFor(OrderSession $session, CartContext $cart, string $flag): Collection
    {
        $productIds = collect($cart->cart())->pluck('product_id')->unique();

        return Product::query()
            ->where('company_id', $session->company_id)
            ->whereIn('id', $productIds)
            ->where($flag, false)
            ->pluck('name');
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

            $branch = $session->branch;
            $price = $branch ? $this->pricing->priceFor($branch, $cart->total(), $lat, $lng) : ['delivery_charge' => 0];

            if ($price['out_of_zone'] ?? false) {
                $this->sender->send(
                    $session->conversation,
                    "Sorry, that location is outside our delivery area. Please share a different location, type an address, or reply \"pickup\" to switch to pickup instead."
                );

                return true;
            }

            $cart->setDeliveryCoordinates($lat, $lng);
            $cart->setCustomer('delivery_address', trim($inboundMessage->text ?? '') ?: 'Shared location');
            $cart->setFulfillment('delivery', $price['delivery_charge']);
            $cart->setDeliveryCoordinates($lat, $lng);

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
