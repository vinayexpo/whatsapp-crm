<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;

interface CatalogSyncServiceInterface
{
    /**
     * Fetch the current set of products from the given Meta product catalog
     * and return them as plain arrays ready to be upserted into products.
     *
     * @return array<int, array{retailer_id: string, name: string, description: ?string, price_minor: int, availability: string, image_url: ?string}>
     */
    public function fetchProducts(ApiConnection $connection, string $catalogId): array;
}
