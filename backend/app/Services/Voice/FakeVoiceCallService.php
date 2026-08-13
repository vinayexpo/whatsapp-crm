<?php

namespace App\Services\Voice;

use App\Models\ApiConnection;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeVoiceCallService implements VoiceCallServiceInterface
{
    public function placeCall(VoiceCall $call, ApiConnection $connection): string
    {
        $callSid = 'fake-call-'.Str::uuid();

        Log::info('FakeVoiceCallService: simulated outbound call', [
            'voice_call_id' => $call->id,
            'contact_id' => $call->contact_id,
            'provider_call_sid' => $callSid,
        ]);

        return $callSid;
    }

    public function buildGatherResponse(VoiceAgent $agent, string $prompt): string
    {
        return $prompt;
    }

    public function buildHangupResponse(VoiceAgent $agent, string $message): string
    {
        return $message;
    }
}
