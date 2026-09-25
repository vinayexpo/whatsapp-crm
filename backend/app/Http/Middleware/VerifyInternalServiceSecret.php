<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalServiceSecret
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.voice_sidecar.shared_secret');

        if (empty($expected)) {
            abort(404);
        }

        $provided = $request->header('X-Internal-Secret', '');

        if (! hash_equals($expected, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
