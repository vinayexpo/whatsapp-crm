<?php

namespace App\Services\Voice;

use App\Models\ApiConnection;

class VoiceCallDriverResolver
{
    public function forConnection(?ApiConnection $connection): VoiceCallServiceInterface
    {
        return $connection && $connection->access_token
            ? new TwilioVoiceCallService
            : new FakeVoiceCallService;
    }
}
