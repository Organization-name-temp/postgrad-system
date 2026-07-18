<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Events\AdmissionAccepted;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Final decision + acceptance mint + Student anchor (ticket 10) — the phase's climax,
| driven end-to-end through /api/v1. The Dean records Accepted / Conditionally Accepted
| / Waitlisted / Rejected from `interview_completed`; accepting mints a Student (a
| University ID, R01→R02, an advisor, a resolvable Student anchor) atomically and fires
| the AdmissionAccepted event whose listener notifies the applicant. Held offers resolve
| to Accepted (which mints) or Rejected. Everything is asserted as observable behaviour —
| HTTP bodies, /me, notification rows, audit rows — never module internals.
*/

/**
 * A decidable application (default `interview_completed`) whose primary Program sits in
 * the given Faculty, owned by an Applicant (R01) so acceptance can be seen to replace
 * the role. Returns [Application, applicant User, Program].
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: Application, 1: User, 2: Program}
 */
function decidableApplication(Faculty $faculty, array $overrides = []): array
{
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);
    $application = Application::factory()->interviewScheduled()->create(array_merge([
        'status' => ApplicationStatus::InterviewCompleted,
        'primary_program_id' => $program->id,
    ], $overrides));
    $applicant = userOf($application);
    $applicant->assignRole(Role::Applicant);

    return [$application, $applicant, $program];
}

/** A Dean (R07 — holds `applicant.decide`) assigned to the given Faculty. */
function decidingDean(Faculty $faculty): User
{
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    return $dean;
}

/** A fresh academic advisor (R03). */
function decisionAdvisor(): User
{
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);

    return $advisor;
}

it('accepts an applicant and mints a Student', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant, $program] = decidableApplication($faculty);
    $advisor = decisionAdvisor();
    Sanctum::actingAs(decidingDean($faculty));

    $response = $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('meta.university_id', fn ($value) => is_string($value)
            && preg_match('/^\d{4}-.+-\d{4}$/', $value) === 1);

    $universityId = $response->json('meta.university_id');
    expect($universityId)->toContain($program->code)->toStartWith((string) $application->admissionCycle()->firstOrFail()->year);

    // The Student anchor exists, tied to the accepted user, the chosen advisor, and the
    // minted University ID.
    $this->assertDatabaseHas('students', [
        'user_id' => $applicant->id,
        'university_id' => $universityId,
        'advisor_id' => $advisor->id,
        'program_id' => $program->id,
    ]);

    // The Student is resolvable through the Identity\Public read facade — by owning
    // user and by the minted University ID.
    $directory = app(StudentDirectory::class);
    expect($directory->find((int) $applicant->id)?->universityId)->toBe($universityId);
    expect($directory->findByUniversityId($universityId)?->userId)->toBe((int) $applicant->id);

    // The role was replaced, not merely added: R01 gone, R02 held.
    expect($applicant->fresh()->hasRole(Role::Student))->toBeTrue();
    expect($applicant->fresh()->hasRole(Role::Applicant))->toBeFalse();

    // An Identity audit entry records the mint.
    $this->assertDatabaseHas('audit_log', [
        'action' => 'student.admitted',
        'subject_type' => 'student',
    ]);

    // The applicant is notified of the acceptance, carrying their University ID.
    Sanctum::actingAs(User::findOrFail($applicant->id));
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'application.accepted')
        ->assertJsonPath('data.0.data.university_id', $universityId);
});

it('surfaces the University ID and Student role on /me after acceptance', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    decisionAdvisor();
    Sanctum::actingAs(decidingDean($faculty));

    $universityId = $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertOk()
        ->json('meta.university_id');

    // The accepted applicant re-signs-in as a Student and sees their identity.
    Sanctum::actingAs(User::findOrFail($applicant->id));
    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.student.university_id', $universityId)
        ->assertJsonPath('data.roles', fn ($roles) => collect($roles)->pluck('code')->contains('R02'))
        ->assertJsonPath('data.student.advisor_id', fn ($value) => is_int($value));
});

it('dispatches the AdmissionAccepted event on acceptance', function () {
    Event::fake([AdmissionAccepted::class]);

    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    decisionAdvisor();
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertOk();

    // The mint still runs (it is in-transaction, not the event); only the event is faked.
    $this->assertDatabaseHas('students', ['user_id' => $applicant->id]);
    Event::assertDispatched(AdmissionAccepted::class, fn (AdmissionAccepted $event) => $event->userId === (int) $applicant->id);
});

it('mints per-(year, program) University IDs that increment and are unique', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $program = Program::factory()->create(['department_id' => $department->id, 'degree_level' => DegreeLevel::MSc]);
    // One shared Cycle, so both admissions land in the same (year, program) bucket and
    // the sequence increments rather than restarting.
    $cycle = AdmissionCycle::factory()->open()->create();
    decisionAdvisor();
    $dean = decidingDean($faculty);

    $ids = collect(range(1, 2))->map(function () use ($program, $cycle, $dean): string {
        $application = Application::factory()->interviewScheduled()->create([
            'status' => ApplicationStatus::InterviewCompleted,
            'admission_cycle_id' => $cycle->id,
            'primary_program_id' => $program->id,
        ]);
        Sanctum::actingAs($dean);

        return $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
            ->assertOk()
            ->json('meta.university_id');
    });

    expect($ids->unique())->toHaveCount(2);
    expect($ids[0])->toEndWith('-0001');
    expect($ids[1])->toEndWith('-0002');
});

it('conditionally accepts and holds the application with its conditions', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'conditional',
        'conditions' => 'Submit the final transcript by September.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'conditionally_accepted')
        ->assertJsonPath('data.conditions', 'Submit the final transcript by September.');

    // Held short of admission — no Student minted.
    $this->assertDatabaseMissing('students', ['user_id' => $applicant->id]);

    Sanctum::actingAs(User::findOrFail($applicant->id));
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'decision.recorded');
});

it('waitlists and holds the application with a position', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'waitlist',
        'waitlist_position' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'waitlisted')
        ->assertJsonPath('data.waitlist_position', 3);

    $this->assertDatabaseMissing('students', ['user_id' => $applicant->id]);
});

it('rejects with a reason and notifies the applicant', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'reject',
        'note' => 'The panel did not recommend admission.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseMissing('students', ['user_id' => $applicant->id]);

    Sanctum::actingAs(User::findOrFail($applicant->id));
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'decision.recorded');
});

it('resolves a conditional offer to accepted, minting the Student', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty, [
        'status' => ApplicationStatus::ConditionallyAccepted,
        'conditions' => 'Submit the final transcript.',
    ]);
    decisionAdvisor();
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $this->assertDatabaseHas('students', ['user_id' => $applicant->id]);
    expect($applicant->fresh()->hasRole(Role::Student))->toBeTrue();
});

it('resolves a conditional offer to rejected', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty, [
        'status' => ApplicationStatus::ConditionallyAccepted,
    ]);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'reject',
        'note' => 'Conditions were not met.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseMissing('students', ['user_id' => $applicant->id]);
});

it('resolves a waitlisted applicant to accepted, minting the Student', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty, [
        'status' => ApplicationStatus::Waitlisted,
        'waitlist_position' => 1,
    ]);
    decisionAdvisor();
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $this->assertDatabaseHas('students', ['user_id' => $applicant->id]);
});

it('resolves a waitlisted applicant to rejected', function () {
    $faculty = Faculty::factory()->create();
    [$application] = decidableApplication($faculty, [
        'status' => ApplicationStatus::Waitlisted,
    ]);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'reject',
        'note' => 'The cycle has closed.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('pre-fills the least-loaded advisor when none is provided', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);

    $busy = decisionAdvisor();
    $free = decisionAdvisor();
    Student::factory()->create(['advisor_id' => $busy->id]); // $busy now has one advisee; $free has none.

    Sanctum::actingAs(decidingDean($faculty));
    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])->assertOk();

    expect(Student::query()->where('user_id', $applicant->id)->firstOrFail()->advisor_id)->toBe((int) $free->id);
});

it('assigns an overridden advisor when one is provided', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    decisionAdvisor();
    $chosen = decisionAdvisor();

    Sanctum::actingAs(decidingDean($faculty));
    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'accept',
        'advisor_id' => $chosen->id,
    ])->assertOk();

    expect(Student::query()->where('user_id', $applicant->id)->firstOrFail()->advisor_id)->toBe((int) $chosen->id);
});

it('rejects an advisor that does not hold the advisor role (422)', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = decidableApplication($faculty);
    $notAdvisor = User::factory()->create(); // holds no role
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'accept',
        'advisor_id' => $notAdvisor->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('advisor_id');

    $this->assertDatabaseMissing('students', ['user_id' => $applicant->id]);
    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewCompleted);
});

it('refuses a decision on an application outside the Dean’s faculty (403)', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    [$application] = decidableApplication($other);
    decisionAdvisor();
    Sanctum::actingAs(decidingDean($mine));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewCompleted);
});

it('refuses the decision surface to a user without applicant.decide (403)', function () {
    $faculty = Faculty::factory()->create();
    [$application] = decidableApplication($faculty);

    // Student Affairs holds application.screen + interview.schedule, but never applicant.decide.
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", ['action' => 'accept'])
        ->assertForbidden();
    $this->getJson('/api/v1/dean/advisors')->assertForbidden();
});

it('refuses an action illegal from the current status (409)', function () {
    $faculty = Faculty::factory()->create();
    // Still awaiting the interview — no decision is legal yet.
    [$application] = decidableApplication($faculty, ['status' => ApplicationStatus::InterviewScheduled]);
    Sanctum::actingAs(decidingDean($faculty));

    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'conditional',
        'conditions' => 'Anything.',
    ])->assertStatus(409);

    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewScheduled);
});

it('lists advisors with the least-loaded one suggested', function () {
    $faculty = Faculty::factory()->create();
    $busy = decisionAdvisor();
    $free = decisionAdvisor();
    Student::factory()->create(['advisor_id' => $busy->id]);

    Sanctum::actingAs(decidingDean($faculty));

    $this->getJson('/api/v1/dean/advisors')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.suggested_advisor_id', (int) $free->id);
});

it('validates the detail each held/rejected decision requires', function () {
    $faculty = Faculty::factory()->create();
    $dean = decidingDean($faculty);

    [$conditional] = decidableApplication($faculty);
    Sanctum::actingAs($dean);
    $this->postJson("/api/v1/dean/applications/{$conditional->id}/decide", ['action' => 'conditional'])
        ->assertStatus(422)->assertJsonValidationErrors('conditions');

    [$waitlisted] = decidableApplication($faculty);
    Sanctum::actingAs($dean);
    $this->postJson("/api/v1/dean/applications/{$waitlisted->id}/decide", ['action' => 'waitlist'])
        ->assertStatus(422)->assertJsonValidationErrors('waitlist_position');

    [$rejected] = decidableApplication($faculty);
    Sanctum::actingAs($dean);
    $this->postJson("/api/v1/dean/applications/{$rejected->id}/decide", ['action' => 'reject'])
        ->assertStatus(422)->assertJsonValidationErrors('note');
});
