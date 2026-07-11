<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // One canonical user per role: r01@ejust.test … r10@ejust.test.
        // Shared dev password "password".
        foreach (Role::cases() as $role) {
            $user = User::firstOrCreate(
                ['email' => strtolower($role->value).'@ejust.test'],
                ['name' => $role->label(), 'password' => Hash::make('password')],
            );
            $user->assignRole($role);
        }

        // One deliberately multi-role user to exercise the union path.
        $multi = User::firstOrCreate(
            ['email' => 'multi@ejust.test'],
            ['name' => 'Multi Role (Advisor + Instructor)', 'password' => Hash::make('password')],
        );
        $multi->assignRole(Role::Advisor);
        $multi->assignRole(Role::Instructor);
    }
}
