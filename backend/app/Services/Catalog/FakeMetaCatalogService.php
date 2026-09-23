<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Log;

class FakeMetaCatalogService implements CatalogSyncServiceInterface
{
    public function fetchProducts(ApiConnection $connection, string $catalogId): array
    {
        Log::info('FakeMetaCatalogService: simulated catalog sync', [
            'api_connection_id' => $connection->id,
            'catalog_id' => $catalogId,
        ]);

        return [
            [
                'retailer_id' => 'fake-sku-001',
                'name' => 'Sample Product One',
                'description' => 'A fake product imported from the Meta catalog sync.',
                'price_minor' => 129900,
                'availability' => 'in stock',
                'image_url' => 'https://example.com/fake-product-1.jpg',
            ],
            [
                'retailer_id' => 'fake-sku-002',
                'name' => 'Sample Product Two',
                'description' => 'Another fake product imported from the Meta catalog sync.',
                'price_minor' => 49900,
                'availability' => 'in stock',
                'image_url' => 'https://example.com/fake-product-2.jpg',
            ],
        ];
    }
}
