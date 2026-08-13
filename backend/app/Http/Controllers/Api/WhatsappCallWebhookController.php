<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappCallStatusUpdated;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundWhatsappCall;
use App\Jobs\ProcessWhatsappCallCompletion;
use App\Models\WebhookEvent;
use App\Models\WhatsappCall;
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

        $status = data_get($request->all(), 'entry.0.changes.0.value.calls.0.status');
        $metaCallId = data_get($request->all(), 'entry.0.changes.0.value.calls.0.id');

        $existingCall = $metaCallId ? WhatsappCall::query()->where('meta_call_id', $metaCallId)->first() : null;

        if (! $existingCall && $status === 'ringing') {
            ProcessInboundWhatsappCall::dispatch($event->id);

            return response()->noContent();
        }

        if ($existingCall) {
            $this->applyStatus($existingCall, $status);
        }

        return response()->noContent();
    }

    public function action(Request $request): Response|JsonResponse
    {
        $metaCallId = $request->input('call_id');
        $whatsappCall = WhatsappCall::query()->where('meta_call_id', $metaCallId)->first();

        if (! $whatsappCall) {
            return response()->noContent(404);
        }

        $speech = $request->input('speech', '');

        if ($speech !== '') {
            $transcript = $whatsappCall->transcript ?? [];
            $transcript[] = ['role' => 'lead', 'text' => $speech, 'at' => now()->toIso8601String()];
            $whatsappCall->update(['transcript' => $transcript, 'status' => 'in_progress']);
        }

        $flow = $whatsappCall->callFlow;
        $nodes = $flow?->nodes ?? [];
        $currentIndex = count($whatsappCall->collected_variables ?? []);

        if (! $flow || $currentIndex >= count($nodes)) {
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

            return response()->json(['action' => 'terminate']);
        }

        $node = $nodes[$currentIndex];

        if (isset($node['variable_key']) && $speech !== '') {
            $variables = $whatsappCall->collected_variables ?? [];
            $variables[$node['variable_key']] = $speech;
            $whatsappCall->update(['collected_variables' => $variables]);
        }

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        if ($node['type'] === 'end_call') {
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

            return response()->json(['action' => 'terminate', 'prompt' => $node['prompt'] ?? null]);
        }

        if ($node['type'] === 'transfer_human') {
            $whatsappCall->update(['needs_human_followup' => true]);
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

            return response()->json(['action' => 'terminate', 'prompt' => $node['prompt'] ?? null]);
        }

        return response()->json([
            'action' => 'prompt',
            'prompt' => $node['prompt'] ?? null,
            'options' => $node['options'] ?? null,
        ]);
    }

    private function applyStatus(WhatsappCall $whatsappCall, ?string $status): void
    {
        $statusMap = [
            'ringing' => 'ringing',
            'accepted' => 'in_progress',
            'terminated' => 'completed',
            'failed' => 'failed',
            'missed' => 'missed',
            'rejected' => 'missed',
        ];

        $newStatus = $statusMap[$status] ?? $whatsappCall->status;
        $attributes = ['status' => $newStatus];

        if ($status === 'accepted' && ! $whatsappCall->started_at) {
            $attributes['started_at'] = now();
        }

        $terminal = in_array($status, ['terminated', 'failed', 'missed', 'rejected'], true);

        if ($terminal) {
            $attributes['ended_at'] = now();

            if (in_array($status, ['failed', 'missed', 'rejected'], true)) {
                $attributes['needs_human_followup'] = true;
            }
        }

        $whatsappCall->update($attributes);

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
