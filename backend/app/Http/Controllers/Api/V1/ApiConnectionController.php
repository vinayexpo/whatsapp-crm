<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiConnectionResource;
use App\Models\ApiConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ApiConnectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ApiConnection::class);

        return ApiConnectionResource::collection(
            ApiConnection::query()->orderBy('channel')->get()
        );
    }

    public function update(Request $request, ApiConnection $apiConnection): JsonResponse
    {
        $this->authorize('update', $apiConnection);

        $data = $request->validate([
            'status' => ['required', 'in:connected,disconnected'],
            'accessToken' => ['nullable', 'string'],
            'wabaId' => ['nullable', 'string'],
            'phoneNumberId' => ['nullable', 'string'],
            'instagramAccountId' => ['nullable', 'string'],
            'twilioAccountSid' => ['nullable', 'string'],
            'twilioPhoneNumber' => ['nullable', 'string'],
            'verifyToken' => ['nullable', 'string'],
        ]);

        if (array_key_exists('verifyToken', $data) && in_array($apiConnection->channel, ['whatsapp', 'instagram'], true)) {
            $apiConnection->update(['verify_token' => $data['verifyToken'] ?: null]);
        }

        if ($data['status'] === 'connected' && $apiConnection->channel === 'voice') {
            $accessToken = $data['accessToken'] ?? $apiConnection->access_token;
            $twilioAccountSid = $data['twilioAccountSid'] ?? $apiConnection->twilio_account_sid;
            $twilioPhoneNumber = $data['twilioPhoneNumber'] ?? $apiConnection->twilio_phone_number;

            if (! $accessToken || ! $twilioAccountSid || ! $twilioPhoneNumber) {
                throw ValidationException::withMessages([
                    'accessToken' => 'A Twilio Account SID, Auth Token, and phone number are needed to connect.',
                ]);
            }

            $this->verifyWithTwilio($twilioAccountSid, $accessToken);

            $apiConnection->update([
                'status' => 'connected',
                'access_token' => $accessToken,
                'twilio_account_sid' => $twilioAccountSid,
                'twilio_phone_number' => $twilioPhoneNumber,
                'account_name' => $apiConnection->label,
                'identifier' => $twilioPhoneNumber,
                'connected_at' => now(),
            ]);
        } elseif ($data['status'] === 'connected') {
            $accessToken = $data['accessToken'] ?? $apiConnection->access_token;
            $wabaId = $data['wabaId'] ?? $apiConnection->waba_id;
            $phoneNumberId = $data['phoneNumberId'] ?? $apiConnection->phone_number_id;
            $instagramAccountId = $data['instagramAccountId'] ?? $apiConnection->instagram_account_id;

            $accountId = $apiConnection->channel === 'instagram' ? $instagramAccountId : $wabaId;

            if (! $accessToken || ! $accountId || ($apiConnection->channel === 'whatsapp' && ! $phoneNumberId)) {
                throw ValidationException::withMessages([
                    'accessToken' => 'An access token and the required account IDs are needed to connect.',
                ]);
            }

            $this->verifyWithGraphApi($accessToken, $accountId);

            $apiConnection->update([
                'status' => 'connected',
                'access_token' => $accessToken,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'instagram_account_id' => $instagramAccountId,
                'account_name' => $apiConnection->label,
                'identifier' => $accountId,
                'connected_at' => now(),
            ]);
        } else {
            $apiConnection->update([
                'status' => 'disconnected',
                'access_token' => null,
                'account_name' => 'Not connected',
                'identifier' => 'Not connected',
                'connected_at' => null,
            ]);
        }

        return response()->json(['data' => new ApiConnectionResource($apiConnection)]);
    }

    public function toggleCalling(Request $request, ApiConnection $apiConnection): JsonResponse
    {
        $this->authorize('update', $apiConnection);

        $data = $request->validate([
            'callingEnabled' => ['required', 'boolean'],
        ]);

        if ($data['callingEnabled'] && ($apiConnection->channel !== 'whatsapp' || $apiConnection->status !== 'connected')) {
            throw ValidationException::withMessages([
                'callingEnabled' => 'The WhatsApp connection must be connected before enabling calling.',
            ]);
        }

        $apiConnection->update([
            'calling_enabled' => $data['callingEnabled'],
            'calling_status' => $data['callingEnabled'] ? 'active' : 'disabled',
            'calling_verified_at' => $data['callingEnabled'] ? now() : null,
        ]);

        return response()->json(['data' => new ApiConnectionResource($apiConnection)]);
    }

    private function verifyWithGraphApi(string $accessToken, string $accountId): void
    {
        try {
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v20.0/{$accountId}", ['fields' => 'id']);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'accessToken' => "Couldn't reach Meta's servers to verify this connection. Check your network and try again.",
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'accessToken' => 'Meta rejected this access token or account ID. Double-check the credentials from Meta Business Manager and try again.',
            ]);
        }
    }

    private function verifyWithTwilio(string $accountSid, string $authToken): void
    {
        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'accessToken' => "Couldn't reach Twilio's servers to verify this connection. Check your network and try again.",
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'accessToken' => 'Twilio rejected this Account SID or Auth Token. Double-check the credentials from the Twilio Console and try again.',
            ]);
        }
    }
}
