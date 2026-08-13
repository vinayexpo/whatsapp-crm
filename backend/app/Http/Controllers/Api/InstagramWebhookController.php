<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundInstagramMessage;
use App\Models\ApiConnection;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InstagramWebhookController extends Controller
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
            ->where('channel', 'instagram')
            ->whereNotNull('verify_token')
            ->get()
            ->contains(fn (ApiConnection $connection) => hash_equals((string) $connection->verify_token, $token));
    }

    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('InstagramWebhookController: rejected webhook with invalid signature');

            return response()->noContent(401);
        }

        $event = WebhookEvent::query()->create([
            'provider' => 'instagram',
            'payload' => $request->all(),
        ]);

        ProcessInboundInstagramMessage::dispatch($event->id);

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
