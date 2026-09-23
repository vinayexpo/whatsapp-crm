<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;

class CatalogDriverResolver
{
    public function forConnection(?ApiConnection $connection): CatalogSyncServiceInterface
    {
        return $connection && $connection->access_token
            ? new GraphApiCatalogService()
            : new FakeMetaCatalogService();
    }
}
