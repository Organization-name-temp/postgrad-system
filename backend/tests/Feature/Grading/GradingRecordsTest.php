<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Public\GradeSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| The Grading\Public GradingRecords read facade (ADR-0020, ticket 04) — the contract Phase
| 5's Academic Standing and Phase 6's Transcript consume. Exercised through the **bound
| contract**, asserting the CGPA/semester GPA figures and GradeSummary DTOs, never Grading's
| Eloquent models (ADR-0012). Prior art: EnrollmentRecordsTest.
| Self-contained helpers (gr* prefix) so a single-file run works.
*/

function gradingRecords(): GradingRecords
{
    return app(GradingRecords::class);
}

function grStudent(): Student
{
    return Student::factory()->create(['user_id' => User::factory()->create()->id]);
}

function grOffering(?Semester $semester = null, ?Course $course = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => ($course ?? Course::factory()->create(['total_ch' => 3]))->id,
        'instructor_id' => User::factory()->create()->id,
    ]);
}

function grEnroll(CourseOffering $offering, Student $student, CourseStatus $status = CourseStatus::Regular, int $creditHours = 3): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $creditHours,
    ]);
}

/** A Grade on a Finalized Sheet for the Enrollment (or, with $status given, some other round). */
function grGrade(Enrollment $enrollment, float $mark, GradeSheetStatus $status = GradeSheetStatus::Finalized): Grade
{
    $sheet = GradeSheet::factory()->inStatus($status)->create([
        'course_offering_id' => $enrollment->course_offering_id,
        'reviewed_at' => $status === GradeSheetStatus::Finalized ? now() : null,
    ]);

    return Grade::factory()->withMark($mark)->create([
        'grade_sheet_id' => $sheet->id,
        'enrollment_id' => $enrollment->id,
    ]);
}

// ---------------------------------------------------------------------------
// cgpaFor
// ---------------------------------------------------------------------------

it('is null for a student with no CGPA-counting graded enrollment', function () {
    $student = grStudent();

    expect(gradingRecords()->cgpaFor($student->id))->toBeNull();
});

it('computes the credit-weighted CGPA over finalized grades', function () {
    $student = grStudent();
    grGrade(grEnroll(grOffering(), $student, creditHours: 1), 91); // A, 4.00, 1 CH
    grGrade(grEnroll(grOffering(), $student, creditHours: 3), 62); // D, 1.00, 3 CH

    // (4.00*1 + 1.00*3) / 4 = 1.75 — a plain mean of the two letters would be 2.50.
    expect(gradingRecords()->cgpaFor($student->id))->toBe(1.75);
});

it('excludes a grade still on a non-finalized sheet from the CGPA', function () {
    $student = grStudent();
    grGrade(grEnroll(grOffering(), $student), 91, GradeSheetStatus::Draft);

    expect(gradingRecords()->cgpaFor($student->id))->toBeNull();
});

it('lets a worse Retake lower the CGPA across the bound contract', function () {
    $student = grStudent();
    $course = Course::factory()->create(['total_ch' => 3]);
    $earlier = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $later = Semester::factory()->create(['starts_at' => now()]);

    grGrade(grEnroll(grOffering($earlier, $course), $student), 95); // A+, 4.00
    grGrade(grEnroll(grOffering($later, $course), $student, CourseStatus::Retake), 62); // D, 1.00

    expect(gradingRecords()->cgpaFor($student->id))->toBe(1.0);
});

// ---------------------------------------------------------------------------
// semesterGpaFor
// ---------------------------------------------------------------------------

it('computes one Semester\'s GPA restricted to that Semester', function () {
    $student = grStudent();
    $semesterA = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $semesterB = Semester::factory()->create(['starts_at' => now()]);
    grGrade(grEnroll(grOffering($semesterA), $student), 91); // A, 4.00
    grGrade(grEnroll(grOffering($semesterB), $student), 62); // D, 1.00

    expect(gradingRecords()->semesterGpaFor($student->id, $semesterA->id))->toBe(4.0)
        ->and(gradingRecords()->semesterGpaFor($student->id, $semesterB->id))->toBe(1.0);
});

it('is null for a semester with no counted enrollment for this student', function () {
    $student = grStudent();
    $semester = Semester::factory()->create();

    expect(gradingRecords()->semesterGpaFor($student->id, $semester->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// gradesFor
// ---------------------------------------------------------------------------

it('returns the current grade for each enrollment id, keyed by id', function () {
    $student = grStudent();
    $enrollment = grEnroll(grOffering(), $student);
    grGrade($enrollment, 82.5);

    $summaries = gradingRecords()->gradesFor([$enrollment->id]);

    expect($summaries[$enrollment->id])->toBeInstanceOf(GradeSummary::class)
        ->and($summaries[$enrollment->id]->letterGrade)->toBe('B')
        ->and($summaries[$enrollment->id]->gpaPoints)->toBe(3.0)
        ->and($summaries[$enrollment->id]->mark)->toBe(82.5);
});

it('omits an id with no finalized grade', function () {
    $student = grStudent();
    $draftEnrollment = grEnroll(grOffering(), $student);
    grGrade($draftEnrollment, 82.5, GradeSheetStatus::Draft);

    expect(gradingRecords()->gradesFor([$draftEnrollment->id]))->toBe([]);
});

it('returns an empty array for an empty id list', function () {
    expect(gradingRecords()->gradesFor([]))->toBe([]);
});

it('batch CGPA parity gate preserves half-up threshold rounding Fall to Spring supersession and null records', function () {
    $boundary = grStudent();
    // (3.30 × 13 + 2.50 × 8) / 21 = 2.995238..., rounded once to 3.00.
    grGrade(grEnroll(grOffering(), $boundary, creditHours: 13), 87);
    grGrade(grEnroll(grOffering(), $boundary, creditHours: 8), 77);
    $retake = grStudent();
    $course = Course::factory()->create();
    $fall = Semester::factory()->create(['starts_at' => '2025-09-01']);
    $spring = Semester::factory()->create(['starts_at' => '2026-02-01']);
    grGrade(grEnroll(grOffering($fall, $course), $retake), 95);
    grGrade(grEnroll(grOffering($spring, $course), $retake, CourseStatus::Retake), 62);
    $empty = grStudent();
    $ids = [$boundary->id, $retake->id, $empty->id];
    $batch = gradingRecords()->cgpaForStudents($ids);
    expect($batch)->toBe([$boundary->id => 3.0, $retake->id => 1.0, $empty->id => null]);
    foreach ($ids as $id) {
        expect($batch[$id])->toBe(gradingRecords()->cgpaFor($id));
    }
});

it('distributes batch CGPA values without conflating an ungraded record with zero', function () {
    $zero = grStudent();
    grGrade(grEnroll(grOffering(), $zero), 40);
    $good = grStudent();
    grGrade(grEnroll(grOffering(), $good), 82);
    $empty = grStudent();
    expect(gradingRecords()->cgpaDistributionForStudents([$zero->id, $good->id, $empty->id]))
        ->toBe(['0.00' => 1, '3.00' => 1, 'ungraded' => 1]);
});
