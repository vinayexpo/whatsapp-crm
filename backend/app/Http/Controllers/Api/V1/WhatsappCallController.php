<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsappCallResource;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use App\Services\Calling\WhatsappCallDriverResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class WhatsappCallController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsappCall::class);

        $query = WhatsappCall::query()
            ->with(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])
            ->orderByDesc('created_at');

        if ($request->filled('callFlowId')) {
            $query->whereHas('callFlow', fn ($q) => $q->where('uuid', $request->query('callFlowId')));
        }

        if ($request->filled('contactId')) {
            $query->whereHas('contact', fn ($q) => $q->where('uuid', $request->query('contactId')));
        }

        if ($request->filled('conversationId')) {
            $query->whereHas('conversation', fn ($q) => $q->where('uuid', $request->query('conversationId')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('needsHumanFollowup') === 'true') {
            $query->where('needs_human_followup', true)->whereNull('human_followup_completed_at');
        }

        return WhatsappCallResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WhatsappCall::class);

        $data = $request->validate([
            'contactId' => ['required', 'string'],
            'conversationId' => ['nullable', 'string'],
            'callFlowId' => ['nullable', 'string'],
        ]);

        $contact = Contact::query()
            ->where('uuid', $data['contactId'])
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $conversation = null;
        if (! empty($data['conversationId'])) {
            $conversation = Conversation::query()
                ->where('uuid', $data['conversationId'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();
        }

        $callFlow = null;
        if (! empty($data['callFlowId'])) {
            $callFlow = WhatsappCallFlow::query()
                ->where('uuid', $data['callFlowId'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();
        }

        $connection = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'connected')
            ->where('calling_enabled', true)
            ->first();

        if (! $connection) {
            throw ValidationException::withMessages([
                'contactId' => 'No WhatsApp connection has calling enabled. Enable calling from Setup before placing a call.',
            ]);
        }

        $call = WhatsappCall::create([
            'whatsapp_call_flow_id' => $callFlow?->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'direction' => 'outbound',
            'status' => 'ringing',
        ]);

        $call->setRelation('contact', $contact);

        try {
            $metaCallId = app(WhatsappCallDriverResolver::class)
                ->forConnection($connection)
                ->placeCall($call, $connection);
        } catch (RequestException) {
            $call->update(['status' => 'failed']);

            throw ValidationException::withMessages([
                'contactId' => "Meta rejected the outbound call request. Double-check the connection's calling setup and try again.",
            ]);
        }

        $call->update(['meta_call_id' => $metaCallId]);

        return response()->json([
            'data' => new WhatsappCallResource($call->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])),
        ], 201);
    }

    public function show(WhatsappCall $whatsappCall): JsonResponse
    {
        $this->authorize('view', $whatsappCall);

        return response()->json(['data' => new WhatsappCallResource(
            $whatsappCall->load(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }

    public function assignFollowup(Request $request, WhatsappCall $whatsappCall): JsonResponse
    {
        $this->authorize('manageFollowup', $whatsappCall);

        $data = $request->validate([
            'userId' => ['nullable', 'string'],
        ]);

        if (empty($data['userId'])) {
            $whatsappCall->update(['human_followup_assigned_to' => null]);
        } else {
            $assignee = User::query()
                ->where('uuid', $data['userId'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();

            $whatsappCall->update(['human_followup_assigned_to' => $assignee->id]);
        }

        return response()->json(['data' => new WhatsappCallResource(
            $whatsappCall->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }

    public function completeFollowup(WhatsappCall $whatsappCall): JsonResponse
    {
        $this->authorize('manageFollowup', $whatsappCall);

        $whatsappCall->update(['human_followup_completed_at' => now()]);

        return response()->json(['data' => new WhatsappCallResource(
            $whatsappCall->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])
        )]);
    }
}
