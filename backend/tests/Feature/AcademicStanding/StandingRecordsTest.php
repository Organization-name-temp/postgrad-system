<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('batch current Standing ignores stale Observations and closed Cases without writing', function () {
    $ungraded = Student::factory()->create();
    $pending = Student::factory()->create();
    $closed = Student::factory()->create();
    StandingObservation::query()->create([
        'student_id' => $ungraded->id,
        'standing' => AcademicStanding::GoodStanding,
        'evidence_fingerprint' => str_repeat('c', 64),
        'cause_type' => 'command',
        'observed_at' => now()->subDay(),
    ]);
    TerminationCase::query()->create([
        'student_id' => $pending->id, 'fingerprint' => str_repeat('a', 64),
        'state' => 'AwaitingCouncil', 'evidence' => ['private' => 'never serialize'],
    ]);
    TerminationCase::query()->create([
        'student_id' => $closed->id, 'fingerprint' => str_repeat('b', 64),
        'state' => 'Dismissed', 'evidence' => [],
    ]);
    $records = app(StandingRecords::class);
    $ids = [$ungraded->id, $pending->id, $closed->id];
    $values = $records->currentForStudents($ids);
    expect(array_keys($values))->toBe($ids)
        ->and($values[$ungraded->id]->standing)->toBe(AcademicStanding::NotYetAssessed)
        ->and($values[$ungraded->id]->cgpa)->toBeNull()
        ->and($values[$pending->id]->standing)->toBe(AcademicStanding::TerminationPending)
        ->and($values[$closed->id]->standing)->toBe(AcademicStanding::NotYetAssessed);
    foreach ($ids as $id) {
        expect($values[$id])->toEqual($records->currentForStudent($id));
    }
    expect($records->currentForStudents([]))->toBe([])
        ->and(StandingObservation::query()->count())->toBe(1)
        ->and(TerminationCase::query()->count())->toBe(2);
});

it('matches single current Standing across the seeded 180 Student cohort without creating observations', function () {
    $this->seed(DatabaseSeeder::class);
    $users = User::query()->where('email', 'like', 'cohort.student.%@seed.ejust.test')->pluck('id');
    $ids = Student::query()->whereIn('user_id', $users)->orderBy('id')->pluck('id')->all();
    expect($ids)->toHaveCount(180);
    $observationCount = StandingObservation::query()->count();
    $caseCount = TerminationCase::query()->count();
    $records = app(StandingRecords::class);
    $values = $records->currentForStudents($ids);
    expect(array_keys($values))->toBe($ids);
    foreach ($ids as $id) {
        expect($values[$id])->toEqual($records->currentForStudent($id));
    }
    expect(StandingObservation::query()->count())->toBe($observationCount)
        ->and(TerminationCase::query()->count())->toBe($caseCount);
});

it('derives all five Standing values from owner grades and keeps zero distinct from null', function () {
    $semester = Semester::factory()->create();
    $graded = function (Student $student, float $mark, int $creditHours = 3, bool $earned = true) use ($semester): void {
        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => Course::factory()->create(['total_ch' => $creditHours])->id,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id, 'course_offering_id' => $offering->id,
            'classification' => 'Core', 'course_status' => 'Regular', 'credit_hours' => $creditHours,
            'earned' => $earned,
        ]);
        $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
            'course_offering_id' => $offering->id, 'reviewed_at' => now(),
        ]);
        Grade::factory()->withMark($mark)->create([
            'grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id,
        ]);
    };
    $ungraded = Student::factory()->create();
    $zero = Student::factory()->create();
    $graded($zero, 40, earned: false);
    $warning = Student::factory()->create();
    $graded($warning, 91, 2); // 4.00 × 2
    $graded($warning, 62, 1, earned: false); // 1.00 × 1; CGPA 3.00, effective D remains unearned
    $good = Student::factory()->create();
    $graded($good, 82); // B, 3.00, earned
    $pending = Student::factory()->create();
    $graded($pending, 91);
    TerminationCase::query()->create([
        'student_id' => $pending->id, 'fingerprint' => str_repeat('d', 64),
        'state' => 'AwaitingAdvisor', 'evidence' => [],
    ]);
    $records = app(StandingRecords::class);
    $values = $records->currentForStudents([$ungraded->id, $zero->id, $warning->id, $good->id, $pending->id]);
    expect($values[$ungraded->id]->standing)->toBe(AcademicStanding::NotYetAssessed)
        ->and($values[$ungraded->id]->cgpa)->toBeNull()
        ->and($values[$zero->id]->standing)->toBe(AcademicStanding::AcademicProbation)
        ->and($values[$zero->id]->cgpa)->toBe(0.0)
        ->and($values[$warning->id]->standing)->toBe(AcademicStanding::AcademicWarning)
        ->and($values[$warning->id]->cgpa)->toBe(3.0)
        ->and($values[$good->id]->standing)->toBe(AcademicStanding::GoodStanding)
        ->and($values[$pending->id]->standing)->toBe(AcademicStanding::TerminationPending);
    foreach ($values as $studentId => $value) {
        expect($value)->toEqual($records->currentForStudent($studentId));
    }
});
