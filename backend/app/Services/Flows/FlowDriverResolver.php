<?php

namespace App\Services\Flows;

use App\Models\ApiConnection;

class FlowDriverResolver
{
    public function forConnection(?ApiConnection $connection): FlowSyncServiceInterface
    {
        return $connection && $connection->access_token
            ? new GraphApiFlowService()
            : new FakeMetaFlowService();
    }
}
