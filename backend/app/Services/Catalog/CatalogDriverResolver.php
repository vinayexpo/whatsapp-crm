<?php

namespace App\Services\Catalog;

use App\Models\ApiConnection;
use Illuminate\Validation\ValidationException;

class CatalogDriverResolver
{
    public function forConnection(?ApiConnection $connection): CatalogSyncServiceInterface
    {
        if (! $connection || ! $connection->access_token) {
            throw ValidationException::withMessages(['apiConnectionId' => 'Connect a WhatsApp account with catalog permissions before syncing.']);
        }

        return new GraphApiCatalogService;
    }
}
