<?php

namespace Modules\Identity\Internal\Directory;

use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;

/**
 * The Eloquent-backed {@see Directory}. Reads the `users` (and `user_roles`) tables
 * and maps each row to a {@see UserSummary}, so Identity's model never crosses the
 * seam (ADR-0012). Bound to the interface in IdentityServiceProvider.
 */
class EloquentDirectory implements Directory
{
    /** @return list<UserSummary> */
    public function usersByRole(Role $role): array
    {
        return User::query()
            ->whereHas('userRoles', fn ($query) => $query->where('role', $role->value))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): UserSummary => $this->toSummary($user))
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, UserSummary>
     */
    public function findMany(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id')
            ->map(fn (User $user): UserSummary => $this->toSummary($user))
            ->all();
    }

    public function isAdministrator(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null
            && ($user->hasRole(Role::SystemAdmin) || $user->hasRole(Role::SuperAdmin));
    }

    private function toSummary(User $user): UserSummary
    {
        return new UserSummary(
            id: (int) $user->getKey(),
            name: (string) $user->name,
            email: (string) $user->email,
        );
    }
}
