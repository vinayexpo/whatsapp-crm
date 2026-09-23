<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\BranchPrice;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Resolves the effective unit price of a product (optionally a specific
 * variant) at a given branch. Resolution order: variant-specific branch
 * override -> product-level branch override -> base price + variant delta ->
 * base price alone.
 */
class ProductPricingResolver
{
    public function resolve(Branch $branch, Product $product, ?ProductVariant $variant = null): int
    {
        $variantPrice = $variant
            ? BranchPrice::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant->id)
                ->value('price')
            : null;

        if ($variantPrice !== null) {
            return $variantPrice;
        }

        $productPrice = BranchPrice::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->value('price');

        if ($productPrice !== null) {
            return $productPrice;
        }

        $base = $product->sale_price ?? $product->base_price;

        return $base + ($variant?->price_delta ?? 0);
    }
}
