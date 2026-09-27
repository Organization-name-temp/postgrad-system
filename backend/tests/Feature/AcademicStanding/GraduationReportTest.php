<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Internal\Models\Notification;

uses(RefreshDatabase::class);

it('summarizes advisory readiness and overlapping blockers across the permitted full population', function () {
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $program = Program::factory()->create();
    $version = CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program, 'subject_id' => $program->id,
        'version_no' => 1, 'changed_at' => now(),
        'snapshot' => ['prep_required_ch' => 0, 'core_required_ch' => 3, 'elective_required_ch' => 0,
            'curriculum' => [['course_id' => 81, 'course_code' => 'CORE-81', 'category' => 'ProgramCore', 'course_total_ch' => 3, 'ordinal' => 1]]],
    ]);
    Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id, 'advisor_id' => $advisor->id]);
    Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => 999999, 'advisor_id' => $advisor->id]);
    Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id]);
    Sanctum::actingAs($advisor);
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness?per_page=1')
        ->assertOk()->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.groups.ready', 0)
        ->assertJsonPath('data.groups.not_ready', 1)
        ->assertJsonPath('data.groups.unavailable', 1)
        ->assertJsonPath('data.blockers.RemainingRequirements', 1)
        ->assertJsonPath('data.blockers.CgpaUnavailable', 1)
        ->assertJsonPath('data.blockers.CgpaBelowMinimum', 0)
        ->assertJsonPath('data.blockers.TerminationPending', 0);
});

it('filters owner-derived readiness before stable pagination and counts only that selection', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::StudentAffairs);
    $program = Program::factory()->create();
    $students = [];
    foreach ([9, 3, 1] as $offset => $remaining) {
        $version = CatalogVersion::query()->create([
            'subject_type' => VersionSubject::Program, 'subject_id' => $program->id,
            'version_no' => $offset + 1, 'changed_at' => now(),
            'snapshot' => ['prep_required_ch' => 0, 'core_required_ch' => $remaining, 'elective_required_ch' => 0,
                'curriculum' => [['course_id' => 81, 'course_code' => 'CORE-81', 'category' => 'ProgramCore', 'course_total_ch' => $remaining, 'ordinal' => 1]]],
        ]);
        $students[] = Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id]);
    }
    Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => 999999]);
    Sanctum::actingAs($actor);
    $criteria = '?proximity=remaining_1_5&blocker=CgpaUnavailable&sort=remaining_ch&per_page=1';
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness/students'.$criteria)
        ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $students[2]->id)
        ->assertJsonPath('data.0.readiness.remaining_credit_hours', 1)
        ->assertJsonPath('data.0.readiness.approaching_graduation', false)
        ->assertJsonPath('data.0.readiness.cgpa', null)
        ->assertJsonCount(2, 'data.0.readiness.blockers');
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness'.$criteria)
        ->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.groups.not_ready', 2);
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness/students?readiness=unavailable')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.readiness', null)
        ->assertJsonPath('data.0.unavailable.0', 'curriculum');
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness?cgpa_min=0')
        ->assertOk()->assertJsonPath('data.total', 0);
    $this->getJson('/api/v1/academic-standing/reports/graduation-readiness?remaining_ch_min=oops')->assertUnprocessable();
});

it('keeps Warning readiness advisory and reflects pending dismissed and cleared Cases without reconciliation', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Council);
    $program = Program::factory()->create();
    $version = CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program, 'subject_id' => $program->id,
        'version_no' => 1, 'changed_at' => now(),
        'snapshot' => ['prep_required_ch' => 0, 'core_required_ch' => 0, 'elective_required_ch' => 0, 'curriculum' => []],
    ]);
    $student = Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id]);
    foreach ([[62, 1, false], [97, 3, true]] as [$mark, $ch, $earned]) {
        $offering = CourseOffering::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id, 'course_offering_id' => $offering->id,
            'classification' => 'Core', 'course_status' => 'Regular', 'credit_hours' => $ch, 'earned' => $earned,
        ]);
        $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
            'course_offering_id' => $offering->id, 'reviewed_at' => now(),
        ]);
        Grade::factory()->withMark($mark)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    }
    Sanctum::actingAs($actor);
    $url = '/api/v1/academic-standing/reports/graduation-readiness/students';
    $this->getJson('/api/v1/academic-standing/reports/standing/students')->assertOk()->assertJsonPath('data.0.standing', 'AcademicWarning');
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.readiness.ready', true)->assertJsonPath('data.0.readiness.cgpa', 3.25);
    $case = TerminationCase::query()->create([
        'student_id' => $student->id, 'fingerprint' => str_repeat('b', 64), 'state' => 'AwaitingAdvisor', 'evidence' => [],
    ]);
    $this->getJson($url.'?blocker=TerminationPending')->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.readiness.ready', false)->assertJsonPath('data.0.readiness.blockers', ['TerminationPending']);
    foreach (['Dismissed', 'TriggerCleared'] as $closedState) {
        $case->update(['state' => $closedState, 'closed_at' => now()]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.readiness.ready', true);
    }
    $student->update(['lifecycle_status' => 'Terminated']);
    $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson($url.'?lifecycle=Terminated')->assertOk()->assertJsonPath('data.0.lifecycle', 'Terminated')->assertJsonPath('data.0.readiness.ready', true);
    expect(TerminationCase::query()->count())->toBe(1)
        ->and($case->fresh()->state)->toBe('TriggerCleared')
        ->and(StandingObservation::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0);
});
