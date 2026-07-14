<?php

namespace Modules\Identity\Internal\Registration;

use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\ApplicantRegistrar;
use Modules\Identity\Public\Enums\Role;

/**
 * Eloquent {@see ApplicantRegistrar}: the only place a self-registering applicant
 * User is minted. The password is hashed by the User model's `hashed` cast; the
 * Applicant role (R01) is assigned through the same {@see User::assignRole()} the
 * seeders use, so the §3.2 matrix stays the single source of a user's rights.
 */
class EloquentApplicantRegistrar implements ApplicantRegistrar
{
    public function register(string $name, string $email, string $plainPassword): int
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
        ]);

        $user->assignRole(Role::Applicant);

        return (int) $user->getKey();
    }
}
