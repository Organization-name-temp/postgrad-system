<?php

use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Tests\TestCase;

/*
| Bind Laravel's TestCase to the Feature suite so feature tests can drive the
| HTTP kernel ($this->getJson(...), etc.). Database-touching tests opt into
| RefreshDatabase per file (from ticket 04 on); Phase 0's tracer needs no DB, so
| it is not applied globally here.
*/

uses(TestCase::class)->in('Feature');

/**
 * Sign in as a fresh user holding the given role. Shared across feature tests
 * (the API is the sole RBAC authority — ADR-0008).
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * A complete, valid Step-1 personal-info snapshot payload; overridable per case.
 * Shared by the wizard and email-verification feature tests (ticket 04).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function snapshotPayload(array $overrides = []): array
{
    return array_merge([
        'name_en' => 'Sara Applicant',
        'name_ar' => 'سارة المتقدمة',
        'national_id' => '29001010101010',
        'phone' => '+201000000000',
        'date_of_birth' => '1998-05-20',
        'nationality' => 'Egyptian',
        'gender' => 'Female',
        'address_country' => 'Egypt',
        'address_city' => 'Alexandria',
        'address_street' => '15 Corniche Road',
        'previous_university' => 'Cairo University',
        'graduation_certificate_name' => 'BSc in Computer Science',
        'graduation_date' => '2020-07-01',
        'previous_degree' => 'BSc',
        'previous_major' => 'Computer Science',
        'graduation_gpa' => 3.50,
        'graduation_cgpa' => 3.45,
        'gpa_scale' => '4.0',
        'is_ejust_graduate' => false,
        'applying_for_degree' => 'MSc',
        'english_test' => 'IELTS',
        'english_score' => 7.5,
        'native_arabic_speaker' => true,
    ], $overrides);
}

/**
 * A fresh unverified applicant owning an empty Draft in an open Cycle — the state
 * self-registration produces. Returns [User, Application, AdmissionCycle].
 *
 * @return array{0: User, 1: Application, 2: AdmissionCycle}
 */
function draftApplicant(?AdmissionCycle $cycle = null): array
{
    $cycle ??= AdmissionCycle::factory()->open()->create();
    $user = User::factory()->unverified()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'admission_cycle_id' => $cycle->id,
    ]);

    return [$user, $application, $cycle];
}

/**
 * The owning User for an Application. The Application model carries no Eloquent
 * relation to User (that model is Identity\Internal — the seam is code-level), so
 * tests resolve the owner by id.
 */
function userOf(Application $application): User
{
    return User::query()->findOrFail($application->user_id);
}

/**
 * A fresh faculty User holding the Instructor role — the interviewer-eligible role
 * (ticket 09). Shared by the interview slot and evaluation feature tests.
 */
function facultyInterviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}
