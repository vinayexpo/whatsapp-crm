<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoiceCallResource;
use App\Models\Contact;
use App\Models\User;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Services\Voice\VoiceCallFinalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VoiceCallController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VoiceCall::class);

        $query = VoiceCall::query()
            ->with(['voiceAgent', 'contact', 'conversation', 'humanFollowupAssignee'])
            ->orderByDesc('created_at');

        if ($request->filled('voiceAgentId')) {
            $query->whereHas('voiceAgent', fn ($q) => $q->where('uuid', $request->query('voiceAgentId')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('needsHumanFollowup') === 'true') {
            $query->where('needs_human_followup', true)->whereNull('human_followup_completed_at');
        }

        return VoiceCallResource::collection($query->get());
    }

    public function show(VoiceCall $voiceCall): JsonResponse
    {
        $this->authorize('view', $voiceCall);

        return response()->json(['data' => new VoiceCallResource(
            $voiceCall->load(['voiceAgent', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }

    public function logSimulatedCall(Request $request, VoiceCallFinalizer $finalizer): JsonResponse
    {
        $this->authorize('create', VoiceCall::class);

        $data = $request->validate([
            'contactId' => ['required', 'string'],
            'voiceAgentId' => ['sometimes', 'nullable', 'string'],
            'transcript' => ['required', 'array'],
            'transcript.*.role' => ['required', 'in:agent,lead'],
            'transcript.*.text' => ['required', 'string'],
            'transcript.*.at' => ['sometimes', 'string'],
            'qualificationFields' => ['sometimes', 'nullable', 'array'],
            'qualificationOutcome' => ['sometimes', 'nullable', 'in:qualified,not_qualified,uncertain'],
            'qualificationSummary' => ['sometimes', 'nullable', 'string'],
        ]);

        $contact = Contact::query()->where('uuid', $data['contactId'])->firstOrFail();

        $voiceAgent = ! empty($data['voiceAgentId'])
            ? VoiceAgent::query()->where('uuid', $data['voiceAgentId'])->first()
            : null;

        $voiceCall = VoiceCall::query()->create([
            'voice_agent_id' => $voiceAgent?->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'medium' => 'browser_simulation',
            'status' => 'completed',
            'transcript' => $data['transcript'],
            'qualification_fields' => $data['qualificationFields'] ?? null,
            'qualification_outcome' => $data['qualificationOutcome'] ?? null,
            'qualification_summary' => $data['qualificationSummary'] ?? null,
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $finalizer->finalize($voiceCall);

        return response()->json(['data' => new VoiceCallResource(
            $voiceCall->fresh(['voiceAgent', 'contact', 'conversation', 'humanFollowupAssignee'])
        )], 201);
    }

    public function assignFollowup(Request $request, VoiceCall $voiceCall): JsonResponse
    {
        $this->authorize('manageFollowup', $voiceCall);

        $data = $request->validate([
            'userId' => ['nullable', 'string'],
        ]);

        if (empty($data['userId'])) {
            $voiceCall->update(['human_followup_assigned_to' => null]);
        } else {
            $assignee = User::query()
                ->where('uuid', $data['userId'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();

            $voiceCall->update(['human_followup_assigned_to' => $assignee->id]);
        }

        return response()->json(['data' => new VoiceCallResource(
            $voiceCall->fresh(['voiceAgent', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }

    public function completeFollowup(VoiceCall $voiceCall): JsonResponse
    {
        $this->authorize('manageFollowup', $voiceCall);

        $voiceCall->update(['human_followup_completed_at' => now()]);

        return response()->json(['data' => new VoiceCallResource(
            $voiceCall->fresh(['voiceAgent', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }
}
