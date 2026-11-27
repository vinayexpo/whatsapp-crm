<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\CommerceSetting;
use App\Models\Conversation;
use App\Models\OrderSession;
use App\Models\Product;
use App\Services\Commerce\Steps\CartReviewStepHandler;
use Illuminate\Support\Facades\DB;

class NativeCatalogCart
{
    public function receive(Conversation $conversation, array $order): bool
    {
        $companyId = $conversation->company_id;
        $settings = CommerceSetting::where('company_id', $companyId)->first();
        $sender = app(CommerceMessageSender::class);
        $branch = Branch::where('company_id', $companyId)->where('status', 'active')->orderByDesc('is_default')->first();
        $items = $order['product_items'] ?? [];
        if (! $branch || ! $settings || $conversation->api_connection_id !== $settings->api_connection_id || (string) ($order['catalog_id'] ?? '') !== $settings->meta_catalog_id || ! is_array($items) || count($items) < 1 || count($items) > 30) {
            $sender->send($conversation, 'We could not open this catalog cart. Please contact the store to review your order.');

            return true;
        }
        // Prices supplied by a customer's cart are never trusted; checkout uses current store prices.
        $session = DB::transaction(function () use ($conversation, $companyId, $branch, $items) {
            Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            OrderSession::where('conversation_id', $conversation->id)->where('status', 'active')->update(['status' => 'abandoned']);
            $session = OrderSession::create(['company_id' => $companyId, 'conversation_id' => $conversation->id, 'contact_id' => $conversation->contact_id, 'branch_id' => $branch->id, 'api_connection_id' => $conversation->api_connection_id, 'status' => 'active', 'step' => 'cart_review', 'context' => [], 'expires_at' => now()->addMinutes(30)]);
            $cart = new CartContext($session);
            $cart->setBranch($branch->id, $branch->name);
            foreach ($items as $item) {
                $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
                $product = Product::where('company_id', $companyId)->where('meta_retailer_id', $item['product_retailer_id'] ?? '')->where('availability', 'in_stock')->where('is_active', true)->first();
                if (! $product || ! $quantity || $quantity < 1 || $quantity > 99) {
                    continue;
                }
                $cart->addItem($product->id, null, $product->name, app(ProductPricingResolver::class)->resolve($branch, $product), $quantity);
            }
            $cart->persist('cart_review');

            return $session;
        });

        return app(CartReviewStepHandler::class)->enter($session);
    }
}
