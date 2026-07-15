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

        // The realistic E-JUST academic catalog the browse tree and later phases run
        // against (Phase 1). Idempotent, so re-seeding the dev DB is safe.
        $this->call(CatalogSeeder::class);

        // The Admissions demo (Phase 2): an open cycle, deans/advisors/interviewers, one
        // Application resting in each status (the Accepted one a real minted Student),
        // interview slots, and sample documents. Runs after the catalog it references.
        $this->call(AdmissionsSeeder::class);
    }
}
