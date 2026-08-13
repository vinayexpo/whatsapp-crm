<?php

namespace App\Services\Calling;

use App\Models\ApiConnection;

class WhatsappCallDriverResolver
{
    public function forConnection(?ApiConnection $connection): WhatsappCallServiceInterface
    {
        return $connection && $connection->calling_enabled && $connection->access_token
            ? new GraphApiWhatsappCallService
            : new FakeWhatsappCallService;
    }
}
