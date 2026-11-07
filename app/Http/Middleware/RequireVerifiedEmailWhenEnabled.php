<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmailWhenEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('samith.require_verified_email') && $request->user() && ! $request->user()->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                abort(403,'Verify your email before using this feature.');
            }
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
