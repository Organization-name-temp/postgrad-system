<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\Calendar\Internal\Models\Semester;
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

it('filters and orders current derived Standing before pagination without exposing private identity', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::StudentAffairs);
    $program = Program::factory()->create();
    $low = Student::factory()->create(['program_id' => $program->id, 'university_id' => '001-LOW']);
    $good = Student::factory()->create(['program_id' => $program->id, 'university_id' => '002-GOOD']);
    $ungraded = Student::factory()->create(['program_id' => $program->id]);
    $semester = Semester::factory()->create();
    foreach ([[$low, 62, false], [$good, 82, true]] as [$student, $mark, $earned]) {
        $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id, 'course_offering_id' => $offering->id,
            'classification' => 'Core', 'course_status' => 'Regular', 'credit_hours' => 3, 'earned' => $earned,
        ]);
        $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
            'course_offering_id' => $offering->id, 'reviewed_at' => now(),
        ]);
        Grade::factory()->withMark($mark)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    }
    StandingObservation::query()->create([
        'student_id' => $low->id, 'standing' => AcademicStanding::GoodStanding,
        'evidence_fingerprint' => str_repeat('a', 64), 'cause_type' => 'command', 'observed_at' => now()->subDay(),
    ]);
    $oldSemester = Semester::factory()->create(['starts_at' => '2024-09-01', 'ends_at' => '2025-01-31']);
    $incomplete = Enrollment::factory()->create([
        'student_id' => $ungraded->id,
        'course_offering_id' => CourseOffering::factory()->create(['semester_id' => $oldSemester->id])->id,
        'classification' => 'Core', 'course_status' => 'Incomplete', 'credit_hours' => 3,
    ]);
    Sanctum::actingAs($actor);
    $base = '/api/v1/academic-standing/reports/standing/students';
    $response = $this->getJson($base.'?sort=-cgpa&per_page=1')->assertOk()
        ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.student_id', $good->id)->assertJsonPath('data.0.cgpa', 3)
        ->assertJsonPath('data.0.standing', 'GoodStanding')
        ->assertJsonPath('data.0.university_id', '002-GOOD');
    expect($response->json('data.0'))->not->toHaveKeys(['national_id', 'identification_number', 'admission_evidence', 'identity']);
    $this->getJson($base.'?sort=-cgpa&per_page=1&page=2')->assertOk()
        ->assertJsonPath('data.0.student_id', $low->id)->assertJsonPath('data.0.cgpa', 1);
    $this->getJson($base.'?sort=-cgpa&per_page=1&page=3')->assertOk()
        ->assertJsonPath('data.0.student_id', $ungraded->id)->assertJsonPath('data.0.cgpa', null);
    $this->getJson($base.'?standing=AcademicProbation&per_page=1')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.student_id', $low->id)
        ->assertJsonPath('data.0.explanation', 'cgpa_below_minimum');
    $this->getJson('/api/v1/academic-standing/reports/standing?standing=AcademicProbation')
        ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.counts.AcademicProbation', 1);
    $this->getJson($base.'?sort=-cgpa&per_page=1')->assertOk();
    expect($incomplete->fresh()->course_status->value)->toBe('Incomplete')
        ->and(Grade::query()->count())->toBe(2)
        ->and(StandingObservation::query()->count())->toBe(1)
        ->and(TerminationCase::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0)
        ->and(Student::query()->where('lifecycle_status', 'Terminated')->count())->toBe(0);
});

it('caps derived pages and uses stable Student IDs across the full candidate population', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Council);
    $students = Student::factory()->count(120)->create(['program_id' => 999999]);
    $ids = $students->pluck('id')->all();
    Sanctum::actingAs($actor);
    $base = '/api/v1/academic-standing/reports/standing/students?sort=-standing&per_page=999';
    $first = $this->getJson($base)->assertOk()->assertJsonPath('meta.total', 120)
        ->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.program', null)->assertJsonPath('data.0.unavailable', ['program']);
    expect(array_column($first->json('data'), 'student_id'))->toBe(array_slice($ids, 0, 100));
    $second = $this->getJson($base.'&page=2')->assertOk()->assertJsonPath('meta.total', 120);
    expect(array_column($second->json('data'), 'student_id'))->toBe(array_slice($ids, 100));
    $this->getJson('/api/v1/academic-standing/reports/standing/students?sort=national_id')->assertUnprocessable();
    $this->getJson('/api/v1/academic-standing/reports/standing?per_page=1')->assertOk()->assertJsonPath('data.total', 120);
    $this->getJson($base)->assertOk();
    expect(StandingObservation::query()->count())->toBe(0)
        ->and(TerminationCase::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0)
        ->and(Grade::query()->count())->toBe(0)
        ->and(Student::query()->where('lifecycle_status', 'Terminated')->count())->toBe(0);
});
