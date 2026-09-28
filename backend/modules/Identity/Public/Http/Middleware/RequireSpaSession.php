<?php

namespace Modules\Identity\Public\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cookie authentication requires Sanctum's first-party session/CSRF pipeline. */
final class RequireSpaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->hasSession(), 400, __('auth.session_required'));

        return $next($request);
    }
}
