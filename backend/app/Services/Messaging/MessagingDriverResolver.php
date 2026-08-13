<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;

class MessagingDriverResolver
{
    public function forConnection(?ApiConnection $connection): OutboundMessageServiceInterface
    {
        return $connection && $connection->access_token
            ? new GraphApiMessagingService()
            : new FakeMetaMessagingService();
    }
}
