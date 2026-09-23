<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Models\ProductVariant;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

/**
 * Handles a variant selection made from ProductBrowseStepHandler's variant
 * list. Products without variants skip this step entirely (ProductBrowse
 * sends straight to variant_addon_selection for quantity capture).
 */
class ProductDetailStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if (! str_starts_with($selection, 'variant:')) {
            $this->sender->send($session->conversation, 'Please choose an option from the list above.');

            return true;
        }

        $variantId = (int) substr($selection, strlen('variant:'));
        $variant = ProductVariant::query()
            ->where('company_id', $session->company_id)
            ->where('id', $variantId)
            ->where('is_active', true)
            ->first();

        if (! $variant) {
            $this->sender->send($session->conversation, 'That option is no longer available.');

            return true;
        }

        $cart = new CartContext($session);
        $cart->setNav('pending_product_id', $variant->product_id);
        $cart->setNav('pending_variant_id', $variant->id);
        $cart->persist('variant_addon_selection');

        $this->sender->send(
            $session->conversation,
            "Selected: {$variant->name}\n\nType a quantity (e.g. \"2\") to add this to your cart, or type \"back\" to go back."
        );

        return true;
    }
}
