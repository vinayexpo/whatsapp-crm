<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\WhatsappCallStatusUpdated;
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
            'sdp_exchange_status' => 'pending_offer',
        ]);

        return response()->json([
            'data' => new WhatsappCallResource($call->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])),
        ], 201);
    }

    public function submitOffer(Request $request, WhatsappCall $whatsappCall): JsonResponse
    {
        $this->authorize('create', WhatsappCall::class);

        $data = $request->validate([
            'sdpOffer' => ['required', 'string'],
        ]);

        $connection = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'connected')
            ->where('calling_enabled', true)
            ->first();

        if (! $connection) {
            throw ValidationException::withMessages([
                'sdpOffer' => 'No WhatsApp connection has calling enabled. Enable calling from Setup before placing a call.',
            ]);
        }

        $whatsappCall->update([
            'local_sdp_offer' => $data['sdpOffer'],
            'sdp_exchange_status' => 'offer_sent',
        ]);

        try {
            $metaCallId = app(WhatsappCallDriverResolver::class)
                ->forConnection($connection)
                ->placeCall($whatsappCall, $connection, $data['sdpOffer']);
        } catch (RequestException $e) {
            $whatsappCall->update(['status' => 'failed', 'sdp_exchange_status' => 'failed']);

            WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

            throw ValidationException::withMessages([
                'sdpOffer' => $this->describeMetaError($e),
            ]);
        }

        $whatsappCall->update(['meta_call_id' => $metaCallId]);

        return response()->json([
            'data' => new WhatsappCallResource($whatsappCall->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])),
        ]);
    }

    public function hangup(WhatsappCall $whatsappCall): JsonResponse
    {
        $this->authorize('create', WhatsappCall::class);

        $connection = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'connected')
            ->where('calling_enabled', true)
            ->first();

        if ($connection && $whatsappCall->meta_call_id) {
            app(WhatsappCallDriverResolver::class)
                ->forConnection($connection)
                ->sendCallAction($whatsappCall, $connection, ['action' => 'terminate']);
        }

        $whatsappCall->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        return response()->json([
            'data' => new WhatsappCallResource($whatsappCall->fresh(['callFlow', 'contact', 'conversation', 'humanFollowupAssignee'])),
        ]);
    }

    private function describeMetaError(RequestException $e): string
    {
        $error = $e->response?->json('error');

        if (! $error) {
            return "Meta rejected the outbound call request. Double-check the connection's calling setup and try again.";
        }

        $message = $error['message'] ?? 'Meta rejected the outbound call request.';
        $details = $error['error_data']['details'] ?? null;
        $code = $error['code'] ?? null;
        $subcode = $error['error_subcode'] ?? null;

        $suffix = $code ? " (code {$code}".($subcode ? "/{$subcode}" : '').')' : '';

        return $details ? "{$message}: {$details}{$suffix}" : "{$message}{$suffix}";
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
