<?php

namespace App\Services\Calling;

use App\Models\ApiConnection;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\Http;

class GraphApiWhatsappCallService implements WhatsappCallServiceInterface
{
    public function placeCall(WhatsappCall $call, ApiConnection $connection, string $sdpOffer): string
    {
        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->phone_number_id}/calls", [
                'messaging_product' => 'whatsapp',
                'to' => $call->contact->handle,
                'action' => 'connect',
                'session' => [
                    'sdp_type' => 'offer',
                    'sdp' => $sdpOffer,
                ],
            ])
            ->throw();

        return $response->json('calls.0.id');
    }

    public function sendCallAction(WhatsappCall $call, ApiConnection $connection, array $action): void
    {
        Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->phone_number_id}/calls", array_merge([
                'messaging_product' => 'whatsapp',
                'call_id' => $call->meta_call_id,
            ], $action))
            ->throw();
    }

    public function sendCallPermissionRequest(WhatsappCall $call, ApiConnection $connection): void
    {
        Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $call->contact->handle,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'call_permission_request',
                ],
            ])
            ->throw();
    }
}
