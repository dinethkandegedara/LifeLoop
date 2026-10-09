<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsOtpVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
