<?php

namespace App\Services\Calling;

use App\Models\ApiConnection;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeWhatsappCallService implements WhatsappCallServiceInterface
{
    public function placeCall(WhatsappCall $call, ApiConnection $connection): string
    {
        $metaCallId = 'fake-whatsapp-call-'.Str::uuid();

        Log::info('FakeWhatsappCallService: simulated outbound call', [
            'whatsapp_call_id' => $call->id,
            'contact_id' => $call->contact_id,
            'meta_call_id' => $metaCallId,
        ]);

        return $metaCallId;
    }

    public function sendCallAction(WhatsappCall $call, ApiConnection $connection, array $action): void
    {
        Log::info('FakeWhatsappCallService: simulated call action', [
            'whatsapp_call_id' => $call->id,
            'action' => $action,
        ]);
    }
}
