<?php

namespace App\Http\Controllers\Api;

use App\Events\VoiceCallStatusUpdated;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundVoiceCall;
use App\Jobs\ProcessVoiceCallCompletion;
use App\Models\ApiConnection;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Models\WebhookEvent;
use App\Services\Voice\VoiceCallQualificationServiceInterface;
use App\Services\Voice\VoiceCallServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioVoiceWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('TwilioVoiceWebhookController: rejected webhook with invalid signature');

            return response('Forbidden', 403);
        }

        $event = WebhookEvent::query()->create([
            'provider' => 'twilio_voice',
            'payload' => $request->all(),
        ]);

        ProcessInboundVoiceCall::dispatch($event->id);

        $toNumber = $request->input('To');
        $connection = ApiConnection::query()->where('channel', 'voice')->where('twilio_phone_number', $toNumber)->first();
        $agent = $connection ? VoiceAgent::query()->where('company_id', $connection->company_id)->where('status', 'active')->first() : null;

        $greeting = $agent?->system_prompt
            ? "Hello, this is an automated call. {$agent->qualification_criteria[0]['question']}"
            : 'Hello, thank you for calling.';

        $service = app(VoiceCallServiceInterface::class);
        $twiml = $agent
            ? $service->buildGatherResponse($agent, $greeting)
            : $service->buildHangupResponse($agent ?? new VoiceAgent, 'Sorry, no agent is available right now. Goodbye.');

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    public function outboundConnect(Request $request, VoiceCall $voiceCall): Response
    {
        $agent = $voiceCall->voiceAgent;
        $service = app(VoiceCallServiceInterface::class);

        if (! $agent) {
            return response($service->buildHangupResponse(new VoiceAgent, 'Goodbye.'), 200)->header('Content-Type', 'text/xml');
        }

        $question = app(VoiceCallQualificationServiceInterface::class)->nextQuestion($agent, $voiceCall);
        $prompt = $question ?? ($agent->system_prompt ?: 'Hello, thank you for your time.');

        $twiml = $question
            ? $service->buildGatherResponse($agent, $prompt)
            : $service->buildHangupResponse($agent, $prompt);

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    public function gather(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $speechResult = $request->input('SpeechResult', '');

        $voiceCall = VoiceCall::query()->where('provider_call_sid', $callSid)->first();
        $service = app(VoiceCallServiceInterface::class);

        if (! $voiceCall || ! $voiceCall->voiceAgent) {
            return response($service->buildHangupResponse(new VoiceAgent, 'Goodbye.'), 200)->header('Content-Type', 'text/xml');
        }

        $agent = $voiceCall->voiceAgent;

        $transcript = $voiceCall->transcript ?? [];
        $transcript[] = ['role' => 'lead', 'text' => $speechResult, 'at' => now()->toIso8601String()];
        $voiceCall->update(['transcript' => $transcript, 'status' => 'in_progress']);

        $qualificationService = app(VoiceCallQualificationServiceInterface::class);
        $question = $qualificationService->nextQuestion($agent, $voiceCall->fresh());

        if ($question) {
            $transcript = $voiceCall->transcript;
            $transcript[] = ['role' => 'agent', 'text' => $question, 'at' => now()->toIso8601String()];
            $voiceCall->update(['transcript' => $transcript]);

            VoiceCallStatusUpdated::dispatch($voiceCall->fresh());

            return response($service->buildGatherResponse($agent, $question), 200)->header('Content-Type', 'text/xml');
        }

        $closing = 'Thank you, that\'s all we need. Have a great day. Goodbye.';
        $transcript = $voiceCall->transcript;
        $transcript[] = ['role' => 'agent', 'text' => $closing, 'at' => now()->toIso8601String()];
        $voiceCall->update(['transcript' => $transcript]);

        VoiceCallStatusUpdated::dispatch($voiceCall->fresh());

        ProcessVoiceCallCompletion::dispatch($voiceCall->id);

        return response($service->buildHangupResponse($agent, $closing), 200)->header('Content-Type', 'text/xml');
    }

    public function status(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');

        $voiceCall = VoiceCall::query()->where('provider_call_sid', $callSid)->first();

        if (! $voiceCall) {
            return response()->noContent();
        }

        $statusMap = [
            'queued' => 'queued',
            'ringing' => 'ringing',
            'in-progress' => 'in_progress',
            'completed' => 'completed',
            'busy' => 'no_answer',
            'no-answer' => 'no_answer',
            'failed' => 'failed',
            'canceled' => 'failed',
        ];

        $newStatus = $statusMap[$callStatus] ?? $voiceCall->status;

        $attributes = ['status' => $newStatus];

        if ($callStatus === 'in-progress' && ! $voiceCall->started_at) {
            $attributes['started_at'] = now();
        }

        $terminal = in_array($callStatus, ['completed', 'busy', 'no-answer', 'failed', 'canceled'], true);

        if ($terminal) {
            $attributes['ended_at'] = now();

            if (in_array($callStatus, ['busy', 'no-answer', 'failed', 'canceled'], true) || empty($voiceCall->qualification_outcome)) {
                $attributes['needs_human_followup'] = true;
            }
        }

        $voiceCall->update($attributes);

        VoiceCallStatusUpdated::dispatch($voiceCall->fresh());

        if ($terminal && $callStatus === 'completed' && empty($voiceCall->qualification_outcome)) {
            ProcessVoiceCallCompletion::dispatch($voiceCall->id);
        }

        return response()->noContent();
    }

    public function recording(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $recordingUrl = $request->input('RecordingUrl');

        VoiceCall::query()->where('provider_call_sid', $callSid)->update(['recording_url' => $recordingUrl]);

        return response()->noContent();
    }

    private function hasValidSignature(Request $request): bool
    {
        $authToken = config('services.twilio.auth_token');

        if (empty($authToken)) {
            return true;
        }

        $signature = $request->header('X-Twilio-Signature', '');
        $url = $request->fullUrl();

        $data = $url;
        foreach ($request->all() as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }
}
