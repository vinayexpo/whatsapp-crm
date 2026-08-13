<?php

namespace App\Services\Templates;

use App\Models\ApiConnection;

class TemplateDriverResolver
{
    public function forConnection(?ApiConnection $connection): TemplateSyncServiceInterface
    {
        return $connection && $connection->access_token
            ? new GraphApiTemplateService()
            : new FakeMetaTemplateService();
    }
}
