<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatbotResource;
use App\Models\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatbotController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Chatbot::class);

        return ChatbotResource::collection(
            Chatbot::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Chatbot::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'allowedOrigins' => ['sometimes', 'nullable', 'array'],
            'allowedOrigins.*' => ['string'],
            'welcomeMessage' => ['sometimes', 'nullable', 'string'],
            'generalFallbackEnabled' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['in:website,instagram,whatsapp'],
        ]);

        $chatbot = Chatbot::query()->create([
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
            'allowed_origins' => $data['allowedOrigins'] ?? null,
            'welcome_message' => $data['welcomeMessage'] ?? null,
            'general_fallback_enabled' => $data['generalFallbackEnabled'] ?? false,
            'channels' => $data['channels'] ?? ['website'],
        ]);

        return response()->json(['data' => new ChatbotResource($chatbot)], 201);
    }

    public function show(Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);

        return response()->json(['data' => new ChatbotResource($chatbot)]);
    }

    public function update(Request $request, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('update', $chatbot);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'allowedOrigins' => ['sometimes', 'nullable', 'array'],
            'allowedOrigins.*' => ['string'],
            'welcomeMessage' => ['sometimes', 'nullable', 'string'],
            'generalFallbackEnabled' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['in:website,instagram,whatsapp'],
        ]);

        $update = [];
        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }
        if (array_key_exists('allowedOrigins', $data)) {
            $update['allowed_origins'] = $data['allowedOrigins'];
        }
        if (array_key_exists('welcomeMessage', $data)) {
            $update['welcome_message'] = $data['welcomeMessage'];
        }
        if (array_key_exists('generalFallbackEnabled', $data)) {
            $update['general_fallback_enabled'] = $data['generalFallbackEnabled'];
        }
        if (array_key_exists('channels', $data)) {
            $update['channels'] = $data['channels'];
        }

        $chatbot->update($update);

        return response()->json(['data' => new ChatbotResource($chatbot)]);
    }

    public function destroy(Chatbot $chatbot): JsonResponse
    {
        $this->authorize('delete', $chatbot);

        $chatbot->delete();

        return response()->json(['message' => 'Chatbot deleted.']);
    }
}
