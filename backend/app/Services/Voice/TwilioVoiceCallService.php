<?php

namespace App\Services\Voice;

use App\Models\ApiConnection;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class TwilioVoiceCallService implements VoiceCallServiceInterface
{
    public function placeCall(VoiceCall $call, ApiConnection $connection): string
    {
        $response = Http::asForm()
            ->withBasicAuth($connection->twilio_account_sid, $connection->access_token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$connection->twilio_account_sid}/Calls.json", [
                'To' => $call->contact->handle,
                'From' => $connection->twilio_phone_number,
                'Url' => URL::to("/api/webhooks/twilio/voice/outbound-connect/{$call->uuid}"),
                'StatusCallback' => URL::to('/api/webhooks/twilio/voice/status'),
                'StatusCallbackEvent' => 'initiated ringing answered completed',
                'Record' => 'true',
                'RecordingStatusCallback' => URL::to('/api/webhooks/twilio/voice/recording'),
            ])
            ->throw();

        return $response->json('sid');
    }

    public function buildGatherResponse(VoiceAgent $agent, string $prompt): string
    {
        $say = $this->say($agent, $prompt);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <Response>
            <Gather input="speech" action="{$this->gatherUrl()}" method="POST" speechTimeout="auto">
                {$say}
            </Gather>
            <Say>We didn't catch that. Goodbye for now.</Say>
        </Response>
        XML;
    }

    public function buildHangupResponse(VoiceAgent $agent, string $message): string
    {
        $say = $this->say($agent, $message);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <Response>
            {$say}
            <Hangup/>
        </Response>
        XML;
    }

    private function say(VoiceAgent $agent, string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        if ($agent->voice_mode === 'recorded' && $agent->greeting_audio_url) {
            return '<Play>'.htmlspecialchars($agent->greeting_audio_url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Play>';
        }

        $voiceAttr = $agent->tts_voice_id ? ' voice="'.htmlspecialchars($agent->tts_voice_id, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"' : '';

        return "<Say{$voiceAttr}>{$escaped}</Say>";
    }

    private function gatherUrl(): string
    {
        return URL::to('/api/webhooks/twilio/voice/gather');
    }
}
