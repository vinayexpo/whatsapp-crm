<?php

namespace App\Services\Calling;

use App\Models\ApiConnection;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        Log::info('GraphApiWhatsappCallService::placeCall raw Meta response', [
            'whatsapp_call_id' => $call->id,
            'to' => $call->contact->handle,
            'phone_number_id' => $connection->phone_number_id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

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
        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $call->contact->handle,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'call_permission_request',
                    'action' => [
                        'name' => 'call_permission_request',
                    ],
                ],
            ])
            ->throw();

        // No local Message row is created for this send, so the async
        // delivery-status webhook Meta posts back (which could report a
        // silent "failed" here even though the initial POST returned 200)
        // has nothing to attach to. Log the raw response so a failure is at
        // least visible, since it would otherwise vanish entirely.
        Log::info('GraphApiWhatsappCallService::sendCallPermissionRequest raw Meta response', [
            'whatsapp_call_id' => $call->id,
            'to' => $call->contact->handle,
            'phone_number_id' => $connection->phone_number_id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);
    }
}
