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

        // The Enrollment demo (Phase 3): two out-of-phase Semesters (Spring 2026 running
        // with attendance history incl. an audit-at-risk enrollment; Fall 2026 with
        // registration open), Offerings over the anchor programs, and students resting in
        // every interesting state. Runs after the Catalog + Admissions seeders it reads.
        $this->call(EnrollmentSeeder::class);

        // The Grading demo (Phase 4): three *completed* Semesters behind the Phase-3 pair — the
        // only way a grade, an overdue Incomplete and its Fail-Withdrawal conversion are all
        // demonstrable on seed day — with Department Heads, Grade Sheets resting in every state,
        // and Students carrying a superseded Retake, a must-retake prep grade, a corrected grade
        // and a failed external elective. Runs last: it reads all three seeders above.
        $this->call(GradingSeeder::class);

        // The canonical role accounts' own academic footprint (phase 4.5): r02@ as a real
        // Student with finalized Grades, r03@ with advisees and a request waiting on them, and
        // multi@ with **both** — the one account that demonstrates the Dashboard's composition
        // rule. Runs last: it adds to the four worlds above and disturbs none of them.
        $this->call(RoleAccountSeeder::class);
    }
}
