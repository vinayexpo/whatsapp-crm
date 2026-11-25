<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        abort_if($user && ($user->suspended_at !== null || ($user->status ?? 'active') !== 'active' || $user->company?->status === 'suspended'), 401, 'This account is not active. Contact your administrator.');

        return $next($request);
    }
}
