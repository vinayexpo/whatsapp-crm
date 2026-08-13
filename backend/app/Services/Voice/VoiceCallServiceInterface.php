<?php

namespace App\Services\Voice;

use App\Models\ApiConnection;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;

interface VoiceCallServiceInterface
{
    /**
     * Place an outbound PSTN call for the given VoiceCall record and return
     * the provider's call SID (used later to match status-callback webhooks
     * back to this call), mirroring OutboundMessageServiceInterface::send().
     */
    public function placeCall(VoiceCall $call, ApiConnection $connection): string;

    /**
     * Build the provider's call-control response (e.g. TwiML) to speak the
     * given prompt and gather the lead's spoken response, used both for the
     * initial greeting and each subsequent turn of the call.
     */
    public function buildGatherResponse(VoiceAgent $agent, string $prompt): string;

    /**
     * Build the provider's call-control response to speak a final message
     * and end the call (used once qualification is complete).
     */
    public function buildHangupResponse(VoiceAgent $agent, string $message): string;
}
