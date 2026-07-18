<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Permission;
use Modules\Identity\Public\Enums\Role;

/**
 * The authenticated-user payload behind GET /api/v1/me (and the login response).
 * Wrapped in the { data: ... } envelope by JsonResource. The `permissions` array
 * is the user's effective §3.2 permission keys — the SPA mirrors them for UX,
 * but the API re-checks every request (ADR-0008).
 *
 * Once a user holds the Student role (R02), `student` carries their `Student` anchor
 * — University ID, program, enrollment term, advisor — resolved through the
 * Identity\Public {@see StudentDirectory} facade (ADR-0013), so a newly-admitted user
 * sees their student identity on sign-in. It is `null` for everyone else.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        // A user's Student anchor, once they hold R02 — resolved through the Public
        // facade, never the Student model (the seam, ADR-0012/0013). No lookup for the
        // vast majority of users, who are not Students.
        $student = $user->hasRole(Role::Student)
            ? app(StudentDirectory::class)->find((int) $user->getKey())
            : null;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'preferred_locale' => $user->preferred_locale,
            'roles' => $user->roles()->map(fn (Role $role): array => [
                'code' => $role->value,
                'name' => $role->label(),
            ])->all(),
            'permissions' => array_map(
                fn (Permission $permission): string => $permission->value,
                $user->permissions(),
            ),
            'student' => $student?->toArray(),
        ];
    }
}
