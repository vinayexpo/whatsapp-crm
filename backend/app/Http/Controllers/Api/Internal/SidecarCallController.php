<?php

namespace App\Http\Controllers\Api\Internal;

use App\Events\WhatsappCallSdpAnswerReceived;
use App\Http\Controllers\Controller;
use App\Models\WhatsappCall;
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
}
