<?php

namespace App\Http\Middleware;

use App\Support\FrontendPageAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendPageEnabled
{
    public function handle(Request $request, Closure $next, string $page): Response
    {
        abort_unless(FrontendPageAvailability::enabled($page), 404);

        return $next($request);
    }
}
