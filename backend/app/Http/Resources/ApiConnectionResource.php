<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'channel' => $this->channel,
            'label' => $this->label,
            'accountName' => $this->account_name,
            'identifier' => $this->identifier,
            'status' => $this->status,
            'wabaId' => $this->waba_id,
            'phoneNumberId' => $this->phone_number_id,
            'instagramAccountId' => $this->instagram_account_id,
            'twilioAccountSid' => $this->twilio_account_sid,
            'twilioPhoneNumber' => $this->twilio_phone_number,
            'callingEnabled' => (bool) $this->calling_enabled,
            'callingStatus' => $this->calling_status,
            'callingVerifiedAt' => $this->calling_verified_at?->toIso8601String(),
            'connectedAt' => $this->connected_at?->toIso8601String(),
            'webhooks' => $this->webhooks(),
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, verifyToken?: string}>
     */
    private function webhooks(): array
    {
        $verifyToken = $this->verify_token ?: config('services.meta.verify_token');

        return match ($this->channel) {
            'whatsapp' => [
                [
                    'label' => 'WhatsApp messages & status webhook',
                    'url' => url('/api/webhooks/whatsapp'),
                    'verifyToken' => $verifyToken,
                ],
                [
                    'label' => 'WhatsApp Calling webhook',
                    'url' => url('/api/webhooks/whatsapp-call'),
                ],
            ],
            'instagram' => [
                [
                    'label' => 'Instagram messages & events webhook',
                    'url' => url('/api/webhooks/instagram'),
                    'verifyToken' => $verifyToken,
                ],
            ],
            'voice' => [
                ['label' => 'Voice call status callback', 'url' => url('/api/webhooks/twilio/voice')],
                ['label' => 'Voice gather callback', 'url' => url('/api/webhooks/twilio/voice/gather')],
                ['label' => 'Voice status callback', 'url' => url('/api/webhooks/twilio/voice/status')],
                ['label' => 'Voice recording callback', 'url' => url('/api/webhooks/twilio/voice/recording')],
            ],
            default => [],
        };
    }
}
