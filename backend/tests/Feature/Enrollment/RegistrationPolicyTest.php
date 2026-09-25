<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStanding\Internal\Support\AcademicStandingReconciler;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\RegistrationPolicy;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentDirectory;

uses(RefreshDatabase::class);

it('uses the latest effective attempt when deriving an approaching-graduation underload exception', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->create(['total_ch' => 3]);
    $version = CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => [
            'prep_required_ch' => 0,
            'core_required_ch' => 3,
            'elective_required_ch' => 0,
            'curriculum' => [[
                'course_id' => $course->id,
                'course_code' => $course->code,
                'course_total_ch' => 3,
                'category' => CourseCategory::ProgramCore->value,
                'preparatory_requirement_role' => null,
                'ordinal' => 0,
            ]],
        ],
        'changed_at' => now(),
    ]);
    $older = Semester::factory()->create(['starts_at' => now()->subYear(), 'ends_at' => now()->subMonths(8)]);
    $newer = Semester::factory()->create(['starts_at' => now()->subMonths(6), 'ends_at' => now()->subMonths(2)]);
    $registrationSemester = Semester::factory()->create(['starts_at' => now()->addMonth(), 'ends_at' => now()->addMonths(5)]);
    $student = Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id, 'enrollment_term' => $older->name_en]);
    $olderOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $older->id]);
    $newerOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $newer->id]);
    $proposedOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $registrationSemester->id]);
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $olderOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => true]);
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $newerOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Retake, 'credit_hours' => 3, 'earned' => false]);
    $summary = app(StudentDirectory::class)->findByStudentId($student->id);
    expect($summary)->not->toBeNull();
    $line = new CartLine($proposedOffering->id, $course->id, $course->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 10, []);
    app(AcademicStandingReconciler::class)->reconcile($student->id, 'test');

    expect(app(RegistrationPolicy::class)->exceptions($summary, $registrationSemester->name_en, [$line])->approachingGraduationCompletion)->toBeTrue();
});
