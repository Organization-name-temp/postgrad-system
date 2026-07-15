<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\Role;
use Tests\TestCase;

/*
| Least-loaded advisor selection (ticket 10), unit-tested directly (spec §Testing
| Decisions): StudentEnrollment::suggestAdvisor picks the R03 user with the fewest
| current advisees, ties broken by ascending id. Touches the DB (advisee counts are
| derived from the students table), so it opts into TestCase + RefreshDatabase.
*/

uses(TestCase::class, RefreshDatabase::class);

/** A fresh academic advisor (R03). */
function anAdvisor(): User
{
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);

    return $advisor;
}

it('returns null when no advisor exists', function () {
    expect(app(StudentEnrollment::class)->suggestAdvisor())->toBeNull();
});

it('breaks a tie at zero advisees on the lowest id', function () {
    $first = anAdvisor();
    anAdvisor(); // higher id, same (zero) load

    expect(app(StudentEnrollment::class)->suggestAdvisor()?->id)->toBe((int) $first->id);
});

it('prefers the advisor with the fewest advisees', function () {
    $busy = anAdvisor();
    $free = anAdvisor();
    Student::factory()->count(2)->create(['advisor_id' => $busy->id]);

    expect(app(StudentEnrollment::class)->suggestAdvisor()?->id)->toBe((int) $free->id);
});

it('ignores non-advisors even with no advisees', function () {
    User::factory()->create(); // holds no role — not eligible
    $advisor = anAdvisor();

    expect(app(StudentEnrollment::class)->suggestAdvisor()?->id)->toBe((int) $advisor->id);
});
