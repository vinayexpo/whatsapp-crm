<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user()?->isSuperadmin() && $request->user()->company_id === null, 403);

        return $next($request);
    }
}
