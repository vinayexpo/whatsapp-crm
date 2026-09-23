<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

/**
 * Captures customer name, then delivery address, in two sub-turns of the
 * same step (distinguished by which customer.* fields are already set).
 */
class CustomerDetailsStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $text = trim($inboundMessage->text ?? '');
        $cart = new CartContext($session);

        if ($text === '') {
            $this->sender->send($session->conversation, 'Please send a text reply.');

            return true;
        }

        if (! $cart->customer('name')) {
            $cart->setCustomer('name', $text);
            $cart->persist('customer_details');

            $this->sender->send($session->conversation, "Thanks, {$text}!");

            return app(DeliveryOrPickupStepHandler::class)->enter($session);
        }

        return app(DeliveryOrPickupStepHandler::class)->handle($session, $inboundMessage);
    }
}
