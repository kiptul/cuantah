<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isEmployee() || $request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
