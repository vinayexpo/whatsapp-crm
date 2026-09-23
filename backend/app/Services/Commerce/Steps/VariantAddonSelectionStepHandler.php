<?php

namespace App\Services\Commerce\Steps;

use App\Models\AddonDefinition;
use App\Models\Message;
use App\Models\OrderSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\ProductPricingResolver;

/**
 * Handles quantity capture for the currently pending product/variant
 * (set by ProductBrowseStepHandler for no-variant products, or by
 * ProductDetailStepHandler after a variant button is chosen), then an
 * optional addon-selection sub-step before adding the line to the cart.
 */
class VariantAddonSelectionStepHandler implements StepHandlerInterface
{
    public function __construct(
        private CommerceMessageSender $sender,
        private CartReviewStepHandler $cartReview,
        private ProductPricingResolver $pricing,
    ) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $cart = new CartContext($session);
        $text = trim($inboundMessage->text ?? '');
        $selection = $inboundMessage->interactive_reply_id ?? $text;

        if (strtolower($text) === 'back') {
            return app(WelcomeStepHandler::class)->enter($session);
        }

        if ($selection === 'addons:done') {
            return $this->addToCart($session, $cart);
        }

        if (str_starts_with($selection, 'addon:')) {
            return $this->toggleAddon($session, $cart, (int) substr($selection, strlen('addon:')));
        }

        if ($cart->nav('awaiting_addons')) {
            $this->sender->send($session->conversation, 'Please choose an addon from the list, or tap "Done" to continue.');

            return true;
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            $this->sender->send($session->conversation, 'Please reply with a valid quantity (e.g. "1" or "2").');

            return true;
        }

        $cart->setNav('pending_quantity', (int) $text);

        return $this->presentAddonsOrAdd($session, $cart);
    }

    private function presentAddonsOrAdd(OrderSession $session, CartContext $cart): bool
    {
        $productId = $cart->nav('pending_product_id');
        $product = Product::query()->where('company_id', $session->company_id)->find($productId);

        if (! $product) {
            $this->sender->send($session->conversation, 'Sorry, that product is no longer available.');

            return app(WelcomeStepHandler::class)->enter($session);
        }

        $addons = $product->addonDefinitions()->where('is_active', true)->get(['addon_definitions.id', 'name', 'price']);

        if ($addons->isEmpty()) {
            return $this->addToCart($session, $cart);
        }

        $cart->setNav('awaiting_addons', true);
        $cart->setNav('selected_addon_ids', []);
        $cart->persist('variant_addon_selection');

        $buttons = $addons->map(fn (AddonDefinition $a) => [
            'id' => 'addon:'.$a->id,
            'label' => "{$a->name} (+{$a->price})",
        ])->push(['id' => 'addons:done', 'label' => 'Done'])->values()->all();

        $this->sender->send($session->conversation, 'Would you like to add any extras?', $buttons);

        return true;
    }

    private function toggleAddon(OrderSession $session, CartContext $cart, int $addonId): bool
    {
        $selected = $cart->nav('selected_addon_ids') ?? [];

        if (in_array($addonId, $selected, true)) {
            $selected = array_values(array_diff($selected, [$addonId]));
        } else {
            $selected[] = $addonId;
        }

        $cart->setNav('selected_addon_ids', $selected);
        $cart->persist('variant_addon_selection');

        $this->sender->send($session->conversation, count($selected).' extra(s) selected. Tap more, or "Done" to continue.');

        return true;
    }

    private function addToCart(OrderSession $session, CartContext $cart): bool
    {
        $productId = $cart->nav('pending_product_id');
        $variantId = $cart->nav('pending_variant_id');
        $quantity = $cart->nav('pending_quantity') ?? 1;
        $selectedAddonIds = $cart->nav('selected_addon_ids') ?? [];

        $product = Product::query()->where('company_id', $session->company_id)->find($productId);

        if (! $product) {
            $this->sender->send($session->conversation, 'Sorry, that product is no longer available.');

            return app(WelcomeStepHandler::class)->enter($session);
        }

        $variant = $variantId
            ? ProductVariant::query()->where('company_id', $session->company_id)->find($variantId)
            : null;

        $unitPrice = $this->pricing->resolve($session->branch, $product, $variant);
        $name = $variant ? "{$product->name} ({$variant->name})" : $product->name;

        $addons = AddonDefinition::query()
            ->where('company_id', $session->company_id)
            ->whereIn('id', $selectedAddonIds)
            ->get(['id', 'name', 'price'])
            ->map(fn (AddonDefinition $a) => [
                'addon_definition_id' => $a->id,
                'name' => $a->name,
                'price' => $a->price,
                'quantity' => 1,
            ])->all();

        $cart->addItem($product->id, $variant?->id, $name, $unitPrice, $quantity, $addons);
        $cart->setNav('pending_product_id', null);
        $cart->setNav('pending_variant_id', null);
        $cart->setNav('pending_quantity', null);
        $cart->setNav('awaiting_addons', false);
        $cart->setNav('selected_addon_ids', []);
        $cart->persist('cart_review');

        $this->sender->send($session->conversation, "Added {$quantity}x {$name} to your cart.");

        return $this->cartReview->enter($session);
    }
}
