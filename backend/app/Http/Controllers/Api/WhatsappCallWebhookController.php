<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappCallSdpAnswerReceived;
use App\Events\WhatsappCallStatusUpdated;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundWhatsappCall;
use App\Jobs\ProcessWhatsappCallCompletion;
use App\Models\WebhookEvent;
use App\Models\WhatsappCall;
use App\Services\Calling\WhatsappCallFlowStepResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappCallWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('WhatsappCallWebhookController: rejected webhook with invalid signature');

            return response()->noContent(401);
        }

        $event = WebhookEvent::query()->create([
            'provider' => 'whatsapp_call',
            'payload' => $request->all(),
        ]);

        $status = data_get($request->all(), 'entry.0.changes.0.value.calls.0.status')
            ?? data_get($request->all(), 'entry.0.changes.0.value.statuses.0.status');
        $status = $status ? strtolower($status) : null;
        $callEvent = data_get($request->all(), 'entry.0.changes.0.value.calls.0.event');
        $callEvent = $callEvent ? strtolower($callEvent) : null;
        $metaCallId = data_get($request->all(), 'entry.0.changes.0.value.calls.0.id')
            ?? data_get($request->all(), 'entry.0.changes.0.value.statuses.0.id');
        $session = data_get($request->all(), 'entry.0.changes.0.value.calls.0.session');

        $existingCall = $metaCallId ? WhatsappCall::query()->where('meta_call_id', $metaCallId)->first() : null;

        $isCallInitiation = $status === 'ringing'
            || ($callEvent === 'connect' && ($session['sdp_type'] ?? null) === 'offer');

        if (! $existingCall && $isCallInitiation) {
            ProcessInboundWhatsappCall::dispatch($event->id);

            return response()->noContent();
        }

        if ($existingCall && $session && ($session['sdp_type'] ?? null) === 'answer' && ! empty($session['sdp'])) {
            $existingCall->update([
                'remote_sdp_answer' => $session['sdp'],
                'sdp_exchange_status' => 'answer_received',
            ]);

            WhatsappCallSdpAnswerReceived::dispatch($existingCall->fresh());
        }

        if ($existingCall) {
            $this->applyStatus($existingCall, $status);
        }

        return response()->noContent();
    }

    public function action(Request $request, WhatsappCallFlowStepResolver $resolver): Response|JsonResponse
    {
        $metaCallId = $request->input('call_id');
        $whatsappCall = WhatsappCall::query()->where('meta_call_id', $metaCallId)->first();

        if (! $whatsappCall) {
            return response()->noContent(404);
        }

        $speech = $request->input('speech', '');

        return response()->json($resolver->resolve($whatsappCall, $speech));
    }

    private function applyStatus(WhatsappCall $whatsappCall, ?string $status): void
    {
        $statusMap = [
            'ringing' => 'ringing',
            'accepted' => 'in_progress',
            'terminated' => 'completed',
            'completed' => 'completed',
            'failed' => 'failed',
            'missed' => 'missed',
            'rejected' => 'missed',
        ];

        $newStatus = $statusMap[$status] ?? $whatsappCall->status;
        $alreadyTerminal = in_array($whatsappCall->status, ['completed', 'failed', 'missed'], true);
        $attributes = [];

        // Meta's status webhook can be delivered out of order relative to the
        // flow resolver's own completion write (e.g. a late "accepted" event
        // arriving after the call already ended) — never downgrade a call
        // that's already terminal back to a non-terminal status.
        if (! $alreadyTerminal || in_array($newStatus, ['completed', 'failed', 'missed'], true)) {
            $attributes['status'] = $newStatus;
        }

        if ($status === 'accepted' && ! $whatsappCall->started_at) {
            $attributes['started_at'] = now();
            $attributes['sdp_exchange_status'] = 'connected';
        }

        $terminal = in_array($status, ['terminated', 'completed', 'failed', 'missed', 'rejected'], true);

        if ($terminal && ! $alreadyTerminal) {
            $attributes['ended_at'] = now();

            if (in_array($status, ['failed', 'missed', 'rejected'], true)) {
                $attributes['sdp_exchange_status'] = 'failed';
                $attributes['needs_human_followup'] = true;
            }
        }

        if ($attributes !== []) {
            $whatsappCall->update($attributes);
        }

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        if ($terminal) {
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);
        }
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.meta.app_secret');

        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
