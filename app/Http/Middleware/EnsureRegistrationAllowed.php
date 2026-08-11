<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRegistrationAllowed
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! config('app.allow_registration', true)) {
            abort(404);
        }

        return $next($request);
    }
}
