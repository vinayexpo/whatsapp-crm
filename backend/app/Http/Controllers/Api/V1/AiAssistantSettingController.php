<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiAssistantSettingResource;
use App\Models\AiAssistantSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiAssistantSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeSettingsManage($request);

        return response()->json(['data' => new AiAssistantSettingResource(AiAssistantSetting::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettingsManage($request);

        $data = $request->validate([
            'baseUrl' => ['string', 'max:255'],
            'apiKey' => ['nullable', 'string'],
            'model' => ['string', 'max:255'],
        ]);

        $setting = AiAssistantSetting::current();

        $setting->update([
            'base_url' => $data['baseUrl'] ?? $setting->base_url,
            'api_key' => $data['apiKey'] ?? $setting->api_key,
            'model' => $data['model'] ?? $setting->model,
        ]);

        return response()->json(['data' => new AiAssistantSettingResource($setting)]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:system,user,assistant'],
            'messages.*.content' => ['required', 'string'],
        ]);

        $setting = AiAssistantSetting::current();

        if (empty($setting->base_url) || empty($setting->api_key) || empty($setting->model)) {
            return response()->json([
                'message' => "AI Assistant isn't configured yet. Add your Base API URL, API key, and model in Settings.",
            ], 422);
        }

        try {
            $response = Http::withToken($setting->api_key)
                ->timeout(30)
                ->post(rtrim($setting->base_url, '/').'/chat/completions', [
                    'model' => $setting->model,
                    'messages' => $data['messages'],
                ]);

            if (! $response->successful()) {
                Log::warning('AI Assistant chat request failed', [
                    'company_id' => $setting->company_id,
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'message' => 'The AI Assistant request failed. Check your API key, base URL, and model in Settings.',
                ], 502);
            }

            $content = $this->extractContent($response->json());

            if (! is_string($content) || trim($content) === '') {
                Log::warning('AI Assistant chat payload was empty or unsupported', [
                    'company_id' => $setting->company_id,
                    'keys' => array_keys($response->json()),
                ]);

                return response()->json(['message' => 'The AI Assistant returned an empty response.'], 502);
            }

            return response()->json(['data' => ['content' => trim($content)]]);
        } catch (Throwable $e) {
            Log::warning('AI Assistant chat request errored', [
                'company_id' => $setting->company_id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Something went wrong contacting the AI Assistant.'], 502);
        }
    }

    private function authorizeSettingsManage(Request $request): void
    {
        if (! $request->user()->can('settings.manage')) {
            throw new AuthorizationException;
        }
    }

    private function extractContent(array $payload): ?string
    {
        $content = data_get($payload, 'choices.0.message.content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        if (is_array($content)) {
            $text = collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }

                    if (! is_array($part)) {
                        return null;
                    }

                    return data_get($part, 'text.value')
                        ?? data_get($part, 'text')
                        ?? data_get($part, 'content');
                })
                ->filter(fn ($part) => is_string($part) && trim($part) !== '')
                ->implode("\n");

            if ($text !== '') {
                return trim($text);
            }
        }

        foreach ([
            data_get($payload, 'output_text'),
            data_get($payload, 'choices.0.text'),
            data_get($payload, 'choices.0.message.text'),
            data_get($payload, 'output.0.content.0.text'),
            data_get($payload, 'output.0.content.0.text.value'),
            data_get($payload, 'message.content.0.text.value'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
