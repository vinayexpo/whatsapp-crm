<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Http;

class GraphApiCatalogService implements CatalogSyncServiceInterface
{
    public function fetchProducts(ApiConnection $connection, string $catalogId): array
    {
        $products = [];
        $url = $this->base()."/{$catalogId}/products";
        $params = [
            'fields' => 'id,retailer_id,name,description,price,currency,availability,image_url,url,condition,gtin,mpn,brand',
            'limit' => 100,
            'locale' => 'en_US',
        ];

        $pages = 0;
        do {
            if (++$pages > 1000) {
                throw new \RuntimeException('Catalog pagination limit reached; no partial import was applied.');
            }
            $response = Http::withToken($connection->access_token)->connectTimeout(5)->timeout(30)->get($url, $params)->throw();

            foreach ($response->json('data', []) as $item) {
                $products[] = [
                    'retailer_id' => (string) ($item['retailer_id'] ?? $item['id']),
                    'currency' => $item['currency'] ?? (explode(' ', trim($item['price'] ?? '0 INR'))[1] ?? 'INR'),
                    'brand' => $item['brand'] ?? null,
                    'name' => $item['name'] ?? '',
                    'description' => $item['description'] ?? null,
                    'price_minor' => $this->parsePriceMinor($item['price'] ?? null),
                    'availability' => $this->normalizeAvailability($item['availability'] ?? null),
                    'image_url' => $item['image_url'] ?? null,
                    'product_url' => $item['url'] ?? null,
                    'condition' => $this->normalizeCondition($item['condition'] ?? null),
                    'gtin' => $item['gtin'] ?? null,
                    'mpn' => $item['mpn'] ?? null,
                ];
            }

            // Rebuild trusted Graph URLs: never follow a provider-supplied URL with our bearer token.
            $after = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
            if ($response->json('paging.next') && ! $after) {
                throw new \RuntimeException('Meta returned incomplete catalog pagination.');
            }
            $params['after'] = $after;
        } while ($after);

        return $products;
    }

    public function pushProduct(ApiConnection $connection, string $catalogId, array $item): void
    {
        $url = $this->base()."/{$catalogId}/items_batch";

        $response = Http::asForm()->withToken($connection->access_token)->connectTimeout(5)->timeout(30)->post($url, [
            'item_type' => 'PRODUCT_ITEM',
            'allow_upsert' => true,
            'requests' => json_encode([
                [
                    'method' => 'UPDATE',
                    'data' => [
                        'retailer_id' => $item['retailer_id'],
                        'name' => $item['name'],
                        'description' => $item['description'] ?? '',
                        'price' => number_format($item['price_minor'] / 100, 2, '.', '').' '.($item['currency'] ?? 'INR'),
                        'brand' => $item['brand'] ?? '',
                        'availability' => $this->denormalizeAvailability($item['availability'] ?? 'in_stock'),
                        'image_url' => $item['image_url'] ?? '',
                        'url' => $item['product_url'] ?? '',
                        'condition' => $item['condition'] ?? 'new',
                        'gtin' => $item['gtin'] ?? '',
                        'mpn' => $item['mpn'] ?? '',
                    ],
                ],
            ]),
        ])->throw();
        if ($response->json('validation_status.0.errors') || ! $response->json('handles')) {
            throw new \RuntimeException('Meta did not accept the product batch. Check required product fields and catalog permissions.');
        }
        // Acceptance is not completion: the reconciler confirms by reading the remote product back.
    }

    public function deleteProduct(ApiConnection $connection, string $catalogId, string $retailerId): void
    {
        Http::asForm()->withToken($connection->access_token)->connectTimeout(5)->timeout(30)
            ->post($this->base()."/{$catalogId}/items_batch", [
                'item_type' => 'PRODUCT_ITEM',
                'requests' => json_encode([['method' => 'DELETE', 'data' => ['retailer_id' => $retailerId]]]),
            ])->throw();
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version', 'v25.0');
    }

    private function parsePriceMinor(?string $price): int
    {
        if (! $price) {
            return 0;
        }

        [$amount] = explode(' ', trim($price), 2) + [null, null];

        $amount = preg_replace('/[^0-9.\-]/', '', (string) $amount);

        return (int) round((float) $amount * 100);
    }

    private function normalizeAvailability(?string $availability): string
    {
        return match ($availability) {
            'out of stock' => 'out_of_stock',
            'preorder' => 'preorder',
            'discontinued' => 'discontinued',
            default => 'in_stock',
        };
    }

    private function denormalizeAvailability(string $availability): string
    {
        return match ($availability) {
            'out_of_stock' => 'out of stock',
            'preorder' => 'preorder',
            'discontinued' => 'discontinued',
            default => 'in stock',
        };
    }

    private function normalizeCondition(?string $condition): string
    {
        return match ($condition) {
            'refurbished' => 'refurbished',
            'used' => 'used',
            default => 'new',
        };
    }
}
