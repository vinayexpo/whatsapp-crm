<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;
use App\Models\Product;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\ProductPricingResolver;

class ProductBrowseStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private CartReviewStepHandler $cartReview) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if ($selection === 'cart:view') {
            return $this->cartReview->enter($session);
        }

        if (! str_starts_with($selection, 'product:')) {
            $this->sender->send($session->conversation, 'Please choose a product from the list above, or type "cart" to review your order.');

            return true;
        }

        $productId = (int) substr($selection, strlen('product:'));
        $product = Product::query()
            ->where('company_id', $session->company_id)
            ->where('id', $productId)
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->first();

        if (! $product) {
            $this->sender->send($session->conversation, 'That product is no longer available.');

            return true;
        }

        return $this->presentProductDetail($session, $product);
    }

    private function presentProductDetail(OrderSession $session, Product $product): bool
    {
        $cart = new CartContext($session);
        $cart->setNav('current_product_id', $product->id);

        $pricing = app(ProductPricingResolver::class);
        $branch = $session->branch;

        if ($product->variants->isNotEmpty()) {
            $cart->setNav('pending_product_id', $product->id);
            $cart->persist('product_detail');

            $buttons = $product->variants->map(fn ($v) => [
                'id' => 'variant:'.$v->id,
                'label' => $v->name,
            ])->values()->all();

            $this->sender->send(
                $session->conversation,
                "{$product->name}\n".($product->description ?? '')."\n\nPlease choose an option:",
                $buttons
            );

            return true;
        }

        $price = $pricing->resolve($branch, $product);

        $this->sender->send(
            $session->conversation,
            "{$product->name}\n".($product->description ?? '')."\nPrice: {$price}\n\nType a quantity (e.g. \"2\") to add this to your cart, or type \"back\" to go back.",
            null
        );

        $cart->setNav('pending_product_id', $product->id);
        $cart->setNav('pending_variant_id', null);
        $cart->persist('variant_addon_selection');

        return true;
    }
}
