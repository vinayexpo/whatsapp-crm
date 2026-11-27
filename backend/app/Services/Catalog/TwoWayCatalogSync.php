<?php

namespace App\Services\Catalog;

use App\Models\CommerceSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TwoWayCatalogSync
{
    public function run(CommerceSetting $settings): array
    {
        $connection = $settings->apiConnection;
        if (! $settings->meta_catalog_id || ! $connection || $connection->company_id !== $settings->company_id || ! $connection->access_token) {
            throw ValidationException::withMessages(['catalog' => 'Choose a connected WhatsApp account and Meta catalog in Commerce setup.']);
        }
        $lock = Cache::lock('commerce-sync:'.$settings->company_id, 90);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['catalog' => 'A catalog sync is already running.']);
        }
        try {
            $service = app(GraphApiCatalogService::class);
            $remote = collect($service->fetchProducts($connection, $settings->meta_catalog_id))->keyBy('retailer_id');
            if ($remote->contains(fn ($item) => $item['currency'] !== $settings->currency)) {
                throw ValidationException::withMessages(['currency' => 'Catalog currency does not match the commerce currency. No import was applied.']);
            }
            $counts = ['created' => 0, 'updated' => 0, 'pushed' => 0, 'conflicts' => 0, 'deleted' => 0];
            $products = Product::withTrashed()->where('company_id', $settings->company_id)->get();
            $keys = $products->map(fn ($p) => $p->meta_retailer_id ?: ($p->sku ?: $p->uuid))->all();
            if (count($keys) !== count(array_unique($keys))) {
                throw ValidationException::withMessages(['sku' => 'Duplicate retailer IDs or SKUs must be fixed before syncing.']);
            }
            foreach ($products as $product) {
                $product->refresh();
                $key = $product->meta_retailer_id ?: ($product->sku ?: $product->uuid);
                $item = $remote->pull($key);
                if ($product->trashed()) {
                    if ($product->meta_sync_state !== 'remote_deleted' && $product->meta_snapshot && $item) {
                        $service->deleteProduct($connection, $settings->meta_catalog_id, $key);
                    }

                    continue;
                }
                $local = $this->local($product, $settings->currency);
                $incoming = $item ? $this->canonical($item) : null;
                $snapshot = $product->meta_snapshot;
                if ($incoming === $local) {
                    $product->update(['meta_snapshot' => $local, 'meta_retailer_id' => $key, 'meta_synced_at' => now(), 'meta_sync_state' => 'synced', 'meta_sync_error' => null]);
                } elseif ($incoming && (! $snapshot || ($local !== $snapshot && $incoming !== $snapshot))) {
                    $product->update(['meta_sync_state' => 'conflict', 'meta_sync_error' => 'Both copies differ. Choose Keep local or Use Meta to resolve.']);
                    $counts['conflicts']++;
                } elseif ($snapshot && ! $incoming && $local !== $snapshot && $product->meta_sync_state !== 'pending') {
                    $product->update(['meta_sync_state' => 'conflict', 'meta_sync_error' => 'Meta removed this product while it was edited locally. Choose Keep local to recreate it.']);
                    $counts['conflicts']++;
                } elseif ($snapshot && ! $incoming && $local === $snapshot && $product->meta_sync_state !== 'pending') {
                    $product->update(['meta_sync_state' => 'remote_deleted']);
                    $product->delete();
                    $counts['deleted']++;
                } elseif ($incoming && $local === $snapshot) {
                    DB::transaction(fn () => $this->import($product, $incoming, $key));
                    $counts['updated']++;
                } else {
                    try {
                        $this->validateForPush($local);
                        $service->pushProduct($connection, $settings->meta_catalog_id, ['retailer_id' => $key] + $local);
                        $product->update(['meta_retailer_id' => $key, 'meta_sync_state' => 'pending', 'meta_sync_error' => null]);
                        $counts['pushed']++;
                    } catch (\Throwable $e) {
                        report($e);
                        $product->update(['meta_sync_state' => 'error', 'meta_sync_error' => 'Meta submission failed. Check product fields, catalog permissions and retry.']);
                    }
                }
            }
            foreach ($remote as $key => $item) {
                DB::transaction(function () use ($settings, $key, $item) {
                    $product = new Product(['sku' => (string) $key]);
                    $product->company_id = $settings->company_id;
                    $this->import($product, $this->canonical($item), (string) $key);
                });
                $counts['created']++;
            }
            $settings->update(['last_sync_at' => now(), 'sync_error' => null]);

            return $counts;
        } catch (\Throwable $e) {
            $settings->update(['sync_error' => 'Sync could not complete. Verify your connection, catalog access and currency, then retry.']);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function local(Product $product, string $currency): array
    {
        return $this->canonical(['name' => $product->name, 'description' => $product->description, 'price_minor' => $product->sale_price ?? $product->base_price,
            'availability' => $product->availability, 'condition' => $product->condition, 'brand' => $product->brand,
            'image_url' => $product->images[0] ?? null, 'product_url' => $product->product_url, 'gtin' => $product->gtin, 'mpn' => $product->mpn, 'currency' => $currency]);
    }

    public function canonical(array $item): array
    {
        $result = [];
        foreach (['name', 'description', 'price_minor', 'currency', 'availability', 'condition', 'image_url', 'product_url', 'brand', 'gtin', 'mpn'] as $key) {
            $result[$key] = $key === 'price_minor' ? (int) ($item[$key] ?? 0) : (string) ($item[$key] ?? '');
        }

        return $result;
    }

    public function import(Product $product, array $item, string $key): void
    {
        if ($product->exists) {
            $current = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($this->local($current, $item['currency']) !== $this->local($product, $item['currency'])) {
                throw ValidationException::withMessages(['product' => 'The product changed during sync. Retry to review the latest copies.']);
            }
        }
        $product->fill(['name' => $item['name'], 'description' => $item['description'], 'base_price' => $item['price_minor'], 'sale_price' => null,
            'availability' => $item['availability'], 'condition' => $item['condition'], 'brand' => $item['brand'], 'gtin' => $item['gtin'], 'mpn' => $item['mpn'],
            'images' => $item['image_url'] ? [$item['image_url']] : [], 'product_url' => $item['product_url'],
            'meta_retailer_id' => $key, 'meta_snapshot' => $item, 'meta_synced_at' => now(), 'meta_sync_state' => 'synced', 'meta_sync_error' => null]);
        $product->save();
    }

    public function validateForPush(array $item): void
    {
        if (! $item['name'] || ! $item['description'] || ! filter_var($item['image_url'], FILTER_VALIDATE_URL) || ! filter_var($item['product_url'], FILTER_VALIDATE_URL) || $item['price_minor'] < 1) {
            throw ValidationException::withMessages(['product' => 'Meta products need a name, description, positive price, image URL and product URL.']);
        }
    }
}
