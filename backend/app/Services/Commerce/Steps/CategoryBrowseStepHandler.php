<?php

namespace App\Services\Commerce\Steps;

use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Message;
use App\Models\OrderSession;
use App\Models\Product;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

class CategoryBrowseStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private CartReviewStepHandler $cartReview) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selection = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if ($selection === 'cart:view') {
            return $this->cartReview->enter($session);
        }

        if (! str_starts_with($selection, 'category:')) {
            $this->sender->send($session->conversation, 'Please choose a category from the list above, or type "cart" to review your order.');

            return true;
        }

        $categoryId = (int) substr($selection, strlen('category:'));
        $category = Category::query()->where('company_id', $session->company_id)->where('id', $categoryId)->first();

        if (! $category) {
            $this->sender->send($session->conversation, 'That category is no longer available.');

            return true;
        }

        return $this->presentProducts($session, $category);
    }

    private function presentProducts(OrderSession $session, Category $category): bool
    {
        $unavailableProductIds = BranchProduct::query()
            ->where('branch_id', $session->branch_id)
            ->where('is_available', false)
            ->pluck('product_id');

        $products = Product::query()
            ->where('company_id', $session->company_id)
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->whereNotIn('id', $unavailableProductIds)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'base_price', 'sale_price']);

        if ($products->isEmpty()) {
            $this->sender->send($session->conversation, 'No products available in this category right now. Please choose another category.');

            return true;
        }

        $cart = new CartContext($session);
        $cart->setNav('current_category_id', $category->id);
        $cart->persist('product_browse');

        $buttons = $products->map(fn (Product $p) => [
            'id' => 'product:'.$p->id,
            'label' => $p->name,
        ])->values()->all();

        $this->sender->send($session->conversation, "Here's what we have in {$category->name}:", $buttons);

        return true;
    }
}
