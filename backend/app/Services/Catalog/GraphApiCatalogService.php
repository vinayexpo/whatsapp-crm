<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Http;

class GraphApiCatalogService implements CatalogSyncServiceInterface
{
    public function fetchProducts(ApiConnection $connection, string $catalogId): array
    {
        $products = [];
        $url = "https://graph.facebook.com/v20.0/{$catalogId}/products";
        $params = [
            'fields' => 'id,retailer_id,name,description,price,availability,image_url',
            'limit' => 100,
        ];

        while ($url) {
            $response = Http::withToken($connection->access_token)->get($url, $params)->throw();

            foreach ($response->json('data', []) as $item) {
                $products[] = [
                    'retailer_id' => (string) ($item['retailer_id'] ?? $item['id']),
                    'name' => $item['name'] ?? '',
                    'description' => $item['description'] ?? null,
                    'price_minor' => $this->parsePriceMinor($item['price'] ?? null),
                    'availability' => $item['availability'] ?? 'in stock',
                    'image_url' => $item['image_url'] ?? null,
                ];
            }

            $url = $response->json('paging.next');
            $params = [];
        }

        return $products;
    }

    public function pushProduct(ApiConnection $connection, string $catalogId, array $item): void
    {
        $url = "https://graph.facebook.com/v20.0/{$catalogId}/items_batch";

        Http::asForm()->withToken($connection->access_token)->post($url, [
            'item_type' => 'PRODUCT_ITEM',
            'requests' => json_encode([
                [
                    'method' => 'UPDATE',
                    'data' => [
                        'retailer_id' => $item['retailer_id'],
                        'name' => $item['name'],
                        'description' => $item['description'] ?? '',
                        'price' => number_format($item['price_minor'] / 100, 2, '.', '') . ' ' . ($item['currency'] ?? 'USD'),
                        'availability' => $item['availability'],
                        'image_url' => $item['image_url'] ?? '',
                    ],
                ],
            ]),
        ])->throw();
    }

    private function parsePriceMinor(?string $price): int
    {
        if (! $price) {
            return 0;
        }

        [$amount] = explode(' ', trim($price), 2) + [null, null];

        return (int) round((float) $amount * 100);
    }
}
