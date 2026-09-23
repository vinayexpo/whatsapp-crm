<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiConnection;
use App\Models\CommerceSetting;
use App\Models\Product;
use App\Services\Catalog\CatalogDriverResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogSyncController extends Controller
{
    public function sync(Request $request, CatalogDriverResolver $resolver): JsonResponse
    {
        $this->authorize('create', Product::class);

        $companyId = $request->user()->company_id;

        $settings = CommerceSetting::query()->where('company_id', $companyId)->first();

        if (! $settings || ! $settings->meta_catalog_id) {
            return response()->json([
                'message' => 'No Meta catalog ID is configured for this company.',
            ], 422);
        }

        $connection = ApiConnection::query()
            ->where('company_id', $companyId)
            ->where('channel', 'whatsapp')
            ->first();

        $items = $resolver->forConnection($connection)->fetchProducts($connection ?? new ApiConnection(), $settings->meta_catalog_id);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->where('sku', $item['retailer_id'])
                ->first();

            if ($product && $product->meta_synced_at && $product->updated_at->gt($product->meta_synced_at)) {
                $skipped++;

                continue;
            }

            $fields = [
                'name' => $item['name'],
                'description' => $item['description'],
                'base_price' => $item['price_minor'],
                'is_active' => $item['availability'] === 'in stock',
                'images' => $item['image_url'] ? [$item['image_url']] : [],
                'meta_retailer_id' => $item['retailer_id'],
                'meta_synced_at' => now(),
            ];

            if ($product) {
                $product->update($fields);
                $updated++;
            } else {
                $product = new Product($fields);
                $product->company_id = $companyId;
                $product->sku = $item['retailer_id'];
                $product->save();
                $created++;
            }
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => count($items),
            ],
        ]);
    }

    public function push(Request $request, Product $product, CatalogDriverResolver $resolver): JsonResponse
    {
        $this->authorize('update', $product);

        $companyId = $request->user()->company_id;

        $settings = CommerceSetting::query()->where('company_id', $companyId)->first();

        if (! $settings || ! $settings->meta_catalog_id) {
            return response()->json([
                'message' => 'No Meta catalog ID is configured for this company.',
            ], 422);
        }

        $connection = ApiConnection::query()
            ->where('company_id', $companyId)
            ->where('channel', 'whatsapp')
            ->first();

        $item = [
            'retailer_id' => $product->meta_retailer_id ?? $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price_minor' => $product->base_price,
            'availability' => $product->is_active ? 'in stock' : 'out of stock',
            'image_url' => $product->images[0] ?? null,
        ];

        $resolver->forConnection($connection)->pushProduct($connection ?? new ApiConnection(), $settings->meta_catalog_id, $item);

        $product->update([
            'meta_retailer_id' => $item['retailer_id'],
            'meta_synced_at' => now(),
        ]);

        return response()->json([
            'data' => ['pushed' => true],
        ]);
    }
}
