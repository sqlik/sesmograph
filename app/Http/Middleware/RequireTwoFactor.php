<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Authenticated users must finish TOTP enrollment before reaching
     * anything else in the panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
