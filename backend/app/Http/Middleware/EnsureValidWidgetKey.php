<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use App\Scopes\CompanyScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidWidgetKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        // Browsers issue an unauthenticated CORS preflight (OPTIONS) before the
        // actual request; it won't carry X-Widget-Key, so it can't be validated
        // against a chatbot. Answer it generically and let the real request
        // (which does carry the key) perform the actual auth/origin check.
        if ($request->getMethod() === 'OPTIONS') {
            $response = response()->noContent();
            $this->applyCorsHeaders($response, $origin);

            return $response;
        }

        $widgetKey = $request->header('X-Widget-Key');

        if (empty($widgetKey)) {
            abort(404);
        }

        $chatbot = Chatbot::withoutGlobalScope(CompanyScope::class)
            ->where('widget_key', $widgetKey)
            ->where('status', 'active')
            ->first();

        if (! $chatbot) {
            abort(404);
        }

        $allowedOrigins = $chatbot->allowed_origins;

        if (! empty($allowedOrigins) && (empty($origin) || ! in_array($origin, $allowedOrigins, true))) {
            abort(404);
        }

        $request->attributes->set('chatbot', $chatbot);

        $response = $next($request);

        $this->applyCorsHeaders($response, $origin);

        return $response;
    }

    private function applyCorsHeaders(Response $response, ?string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Key, X-Visitor-Id');
        $response->headers->set('Vary', 'Origin');
    }
}
