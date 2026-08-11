<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSearchAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() && config('app.require_registration_for_search', false)) {
            return redirect(config('app.allow_registration', true) ? route('register') : route('login'));
        }

        return $next($request);
    }
}
