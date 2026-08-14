<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\ApiConnection;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && is_string($token) && $this->matchesVerifyToken($token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    private function matchesVerifyToken(string $token): bool
    {
        $globalToken = (string) config('services.meta.verify_token');
        if ($globalToken !== '' && hash_equals($globalToken, $token)) {
            return true;
        }

        return ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->whereNotNull('verify_token')
            ->get()
            ->contains(fn (ApiConnection $connection) => hash_equals((string) $connection->verify_token, $token));
    }

    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('WhatsAppWebhookController: rejected webhook with invalid signature');

            return response()->noContent(401);
        }

        // Meta delivers both messages and calls to the same subscribed
        // webhook URL -- there is no separate registration for call events,
        // so payloads carrying entry[].changes[].value.calls must be routed
        // to the call webhook handler instead of the message job, which
        // only reads value.messages/value.statuses and silently ignores
        // anything else.
        if (! empty(data_get($request->all(), 'entry.0.changes.0.value.calls'))) {
            return app(WhatsappCallWebhookController::class)->handle($request);
        }

        $event = WebhookEvent::query()->create([
            'provider' => 'whatsapp',
            'payload' => $request->all(),
        ]);

        ProcessInboundWhatsAppMessage::dispatch($event->id);

        return response()->noContent();
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
