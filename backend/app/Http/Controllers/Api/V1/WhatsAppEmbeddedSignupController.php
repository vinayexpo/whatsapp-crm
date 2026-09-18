<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiConnectionResource;
use App\Models\ApiConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WhatsAppEmbeddedSignupController extends Controller
{
    /**
     * Exchange the Facebook JS SDK's authorization code for a long-lived
     * access token and create a new, separate coexistence ApiConnection —
     * never overwrites an existing manually-connected row for the company.
     */
    public function exchange(Request $request): JsonResponse
    {
        $this->authorize('create', ApiConnection::class);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'wabaId' => ['required', 'string'],
            'phoneNumberId' => ['required', 'string'],
            'waBusinessAppPhoneNumber' => ['nullable', 'string'],
        ]);

        $accessToken = $this->exchangeCodeForToken($data['code']);

        $apiConnection = new ApiConnection([
            'channel' => 'whatsapp',
            'label' => 'WhatsApp Business API (Coexistence)',
            'account_name' => 'WhatsApp Business API (Coexistence)',
            'identifier' => $data['wabaId'],
            'status' => 'connected',
            'access_token' => $accessToken,
            'waba_id' => $data['wabaId'],
            'phone_number_id' => $data['phoneNumberId'],
            'connected_at' => now(),
            'onboarding_type' => 'coexistence',
            'smb_app_linked_at' => now(),
            'wa_business_app_phone_number' => $data['waBusinessAppPhoneNumber'] ?? null,
        ]);
        $apiConnection->company_id = $request->user()->company_id;
        $apiConnection->save();

        return response()->json(['data' => new ApiConnectionResource($apiConnection)], 201);
    }

    private function exchangeCodeForToken(string $code): string
    {
        try {
            $response = Http::asForm()->get('https://graph.facebook.com/v20.0/oauth/access_token', [
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'code' => $code,
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'code' => "Couldn't reach Meta's servers to complete the signup. Check your network and try again.",
            ]);
        }

        if ($response->failed() || ! $response->json('access_token')) {
            throw ValidationException::withMessages([
                'code' => 'Meta rejected the signup authorization code. Please try connecting again.',
            ]);
        }

        return $response->json('access_token');
    }
}
