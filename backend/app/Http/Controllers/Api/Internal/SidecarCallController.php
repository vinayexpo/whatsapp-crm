<?php

namespace App\Http\Controllers\Api\Internal;

use App\Events\WhatsappCallSdpAnswerReceived;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsappCallCompletion;
use App\Models\WhatsappCall;
use App\Services\Calling\CallTurnRecorder;
use App\Services\Calling\WhatsappCallDriverResolver;
use App\Services\Calling\WhatsappCallFlowStepResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidecarCallController extends Controller
{
    public function sdpAnswer(Request $request, WhatsappCall $whatsappCall, WhatsappCallDriverResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'sdp' => ['required', 'string'],
        ]);

        $whatsappCall->update([
            'remote_sdp_answer' => $validated['sdp'],
            'sdp_exchange_status' => 'answer_received',
        ]);

        WhatsappCallSdpAnswerReceived::dispatch($whatsappCall->fresh());

        $connection = $whatsappCall->conversation?->apiConnection;

        $resolver->forConnection($connection)->sendCallAction($whatsappCall, $connection, [
            'action' => 'accept',
            'session' => [
                'sdp_type' => 'answer',
                'sdp' => $validated['sdp'],
            ],
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function nextPrompt(Request $request, WhatsappCall $whatsappCall, WhatsappCallFlowStepResolver $resolver): JsonResponse
    {
        $speech = $request->input('speech', '');

        return response()->json($resolver->resolve($whatsappCall, $speech));
    }

    public function sessionEvent(Request $request, WhatsappCall $whatsappCall): JsonResponse
    {
        $validated = $request->validate([
            'sidecar_session_id' => ['sometimes', 'string'],
        ]);

        if (isset($validated['sidecar_session_id'])) {
            $whatsappCall->update(['sidecar_session_id' => $validated['sidecar_session_id']]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function spoken(Request $request, WhatsappCall $whatsappCall, CallTurnRecorder $turnRecorder): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $transcript = $whatsappCall->transcript ?? [];
        $transcript[] = ['role' => 'ai', 'text' => $validated['text'], 'at' => now()->toIso8601String()];
        $whatsappCall->update(['transcript' => $transcript]);

        $turnRecorder->record($whatsappCall, 'ai', $validated['text']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Called by the sidecar once it has actually closed the call's
     * CallSession (peer connection torn down, closing line already spoken)
     * -- this is the real end of the call, as opposed to
     * WhatsappCallFlowStepResolver::resolve() returning action=terminate,
     * which only means the script/LLM has nothing more to say. Marking
     * completed here (not there) is what keeps the inbox's "Completed"
     * message from appearing while the caller is still on the line.
     */
    public function ended(Request $request, WhatsappCall $whatsappCall): JsonResponse
    {
        if (! in_array($whatsappCall->status, ['completed', 'failed', 'missed'], true)) {
            $whatsappCall->update(['status' => 'completed', 'ended_at' => now()]);
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);
        }

        return response()->json(['status' => 'ok']);
    }
}
