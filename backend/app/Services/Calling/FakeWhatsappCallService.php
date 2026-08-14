<?php

namespace App\Services\Calling;

use App\Jobs\SimulateWhatsappCallAnswer;
use App\Models\ApiConnection;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeWhatsappCallService implements WhatsappCallServiceInterface
{
    public function placeCall(WhatsappCall $call, ApiConnection $connection, string $sdpOffer): string
    {
        $metaCallId = 'fake-whatsapp-call-'.Str::uuid();

        Log::info('FakeWhatsappCallService: simulated outbound call', [
            'whatsapp_call_id' => $call->id,
            'contact_id' => $call->contact_id,
            'meta_call_id' => $metaCallId,
        ]);

        SimulateWhatsappCallAnswer::dispatch($call->id)->delay(now()->addSeconds(2));

        return $metaCallId;
    }

    public function sendCallAction(WhatsappCall $call, ApiConnection $connection, array $action): void
    {
        Log::info('FakeWhatsappCallService: simulated call action', [
            'whatsapp_call_id' => $call->id,
            'action' => $action,
        ]);
    }

    public function sendCallPermissionRequest(WhatsappCall $call, ApiConnection $connection): void
    {
        Log::info('FakeWhatsappCallService: simulated call permission request', [
            'whatsapp_call_id' => $call->id,
            'contact_id' => $call->contact_id,
        ]);
    }
}
