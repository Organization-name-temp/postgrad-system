<?php

namespace Modules\Identity\Internal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require the authenticated user to hold at least one explicitly named persona Role.
 *
 * Permission gates remain the authority for capabilities. This middleware narrows
 * persona-specific endpoints whose permissions are intentionally shared or inherited.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roleCodes): Response
    {
        $user = $request->user();

        $hasAllowedRole = $user instanceof User
            && collect($roleCodes)
                ->map(static fn (string $code): ?Role => Role::tryFrom($code))
                ->filter()
                ->contains(static fn (Role $role): bool => $user->hasRole($role));

        abort_unless($hasAllowedRole, 403);

        return $next($request);
    }
}
