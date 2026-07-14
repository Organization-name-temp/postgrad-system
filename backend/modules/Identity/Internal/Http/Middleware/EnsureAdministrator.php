<?php

namespace Modules\Identity\Internal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Administration gate (ticket 08) — the system's first **role**-gated surface,
 * distinct from the §3.2 permission gates (ADR-0008). It admits only a System
 * Administrator (R09) or Super Administrator (R10); everyone else, authenticated or
 * not, gets a 403. Deliberately a role check, not a new Permission: admin routing
 * (Dean↔Faculty assignment) is an org-administration concern the fixed permission
 * matrix does not model, so no permission is invented for it (spec §Dean↔Faculty
 * scoping, BR-75).
 *
 * Registered as the `admin` route-middleware alias by IdentityServiceProvider and
 * layered after `auth:sanctum`, so it always runs against an authenticated User.
 */
class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || (! $user->hasRole(Role::SystemAdmin) && ! $user->hasRole(Role::SuperAdmin))) {
            abort(403);
        }

        return $next($request);
    }
}
