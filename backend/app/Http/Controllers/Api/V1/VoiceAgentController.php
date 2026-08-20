<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoiceAgentResource;
use App\Models\VoiceAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VoiceAgentController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VoiceAgent::class);

        return VoiceAgentResource::collection(
            VoiceAgent::query()->orderBy('name')->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VoiceAgent::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,paused'],
            'voiceMode' => ['sometimes', 'in:tts,recorded'],
            'ttsVoiceId' => ['sometimes', 'nullable', 'string'],
            'greetingAudioUrl' => ['sometimes', 'nullable', 'string'],
            'qualificationCriteria' => ['sometimes', 'array'],
            'qualificationCriteria.*.key' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.label' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.question' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.type' => ['required_with:qualificationCriteria', 'string'],
            'qualificationPassCriteria' => ['sometimes', 'nullable', 'string'],
            'maxCallAttempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'systemPrompt' => ['sometimes', 'nullable', 'string'],
        ]);

        $voiceAgent = VoiceAgent::query()->create([
            'name' => $data['name'],
            'status' => $data['status'] ?? 'paused',
            'voice_mode' => $data['voiceMode'] ?? 'tts',
            'tts_voice_id' => $data['ttsVoiceId'] ?? null,
            'greeting_audio_url' => $data['greetingAudioUrl'] ?? null,
            'qualification_criteria' => $data['qualificationCriteria'] ?? [],
            'qualification_pass_criteria' => $data['qualificationPassCriteria'] ?? null,
            'max_call_attempts' => $data['maxCallAttempts'] ?? 1,
            'system_prompt' => $data['systemPrompt'] ?? null,
        ]);

        return response()->json(['data' => new VoiceAgentResource($voiceAgent)], 201);
    }

    public function show(VoiceAgent $voiceAgent): JsonResponse
    {
        $this->authorize('view', $voiceAgent);

        return response()->json(['data' => new VoiceAgentResource($voiceAgent)]);
    }

    public function update(Request $request, VoiceAgent $voiceAgent): JsonResponse
    {
        $this->authorize('update', $voiceAgent);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,paused'],
            'voiceMode' => ['sometimes', 'in:tts,recorded'],
            'ttsVoiceId' => ['sometimes', 'nullable', 'string'],
            'greetingAudioUrl' => ['sometimes', 'nullable', 'string'],
            'qualificationCriteria' => ['sometimes', 'array'],
            'qualificationCriteria.*.key' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.label' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.question' => ['required_with:qualificationCriteria', 'string'],
            'qualificationCriteria.*.type' => ['required_with:qualificationCriteria', 'string'],
            'qualificationPassCriteria' => ['sometimes', 'nullable', 'string'],
            'maxCallAttempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'systemPrompt' => ['sometimes', 'nullable', 'string'],
        ]);

        $update = [];
        $map = [
            'name' => 'name',
            'status' => 'status',
            'voiceMode' => 'voice_mode',
            'ttsVoiceId' => 'tts_voice_id',
            'greetingAudioUrl' => 'greeting_audio_url',
            'qualificationCriteria' => 'qualification_criteria',
            'qualificationPassCriteria' => 'qualification_pass_criteria',
            'maxCallAttempts' => 'max_call_attempts',
            'systemPrompt' => 'system_prompt',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $voiceAgent->update($update);

        return response()->json(['data' => new VoiceAgentResource($voiceAgent)]);
    }

    public function destroy(VoiceAgent $voiceAgent): JsonResponse
    {
        $this->authorize('delete', $voiceAgent);

        $voiceAgent->delete();

        return response()->json(['message' => 'Voice agent deleted.']);
    }
}
