<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = is_file(storage_path('app/installed.lock'));

        if ($request->is('installer') || $request->is('installer/*')) {
            return $installed ? redirect()->route('home') : $next($request);
        }

        if (! $installed && ! $request->is('up')) {
            return redirect()->route('installer.show');
        }

        return $next($request);
    }
}
