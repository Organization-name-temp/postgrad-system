<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsAcademicWorld;
use Database\Seeders\Concerns\SeedsGradedHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/** Isolated worked evidence; Cases are created by the owner's trigger reconciliation. */
class Phase7AcademicReportSeeder extends Seeder
{
    use SeedsAcademicWorld;
    use SeedsGradedHistory;

    public function run(): void
    {
        $program = Program::query()->where('code', 'MSC-CE')->firstOrFail();
        $advisor = User::query()->where('email', 'r03@ejust.test')->firstOrFail();
        $head = User::query()->where('email', 'r06@ejust.test')->firstOrFail();
        $instructor = User::firstOrCreate(['email' => 'phase7.report.instructor@seed.ejust.test'], [
            'name' => 'Phase 7 Report Instructor', 'password' => Hash::make('password'),
        ]);
        $instructor->assignRole(Role::Instructor);
        $cycle = (int) AdmissionCycle::query()->where('intake_term', 'Fall')->where('year', 2026)->firstOrFail()->id;
        $courseIds = $program->curriculumEntries()->whereIn('category', [CourseCategory::ProgramCore, CourseCategory::ResearchElective])->pluck('course_id');
        $semester = null;
        $courses = collect();
        foreach (Semester::query()->where('ends_at', '<', now())->orderBy('starts_at')->get() as $candidate) {
            $occupied = CourseOffering::query()->where('semester_id', $candidate->id)
                ->where(fn ($query) => $query->whereNull('instructor_id')->orWhere('instructor_id', '!=', $instructor->id))->pluck('course_id');
            $available = Course::query()->whereIn('id', $courseIds)->whereNotIn('id', $occupied)
                ->where('level', CourseLevel::Master)->where('total_ch', 3)->orderBy('id')->limit(2)->get();
            if ($available->count() === 2) {
                $semester = $candidate;
                $courses = $available;
                break;
            }
        }
        if ($semester === null) {
            throw new \RuntimeException('Report fixtures require two unused graduate Courses in a completed Semester.');
        }
        $offerings = [];
        foreach ($courses as $index => $course) {
            $offerings[] = $this->offering($semester, $course, $instructor, 30, [
                [$index === 0 ? Weekday::Monday : Weekday::Tuesday, '14:00', '16:00'],
            ]);
        }
        $cases = app(TerminationCases::class);
        for ($index = 1; $index <= 3; $index++) {
            $student = $this->student(sprintf('phase7.report.case.%03d@seed.ejust.test', $index),
                "Phase 7 report Student {$index}", "طالب تقرير المرحلة السابعة {$index}", 'male',
                $advisor, $program, $semester->term->value.' '.$semester->year, $cycle, '4000000000000'.$index);
            // Reseeding preserves reviews, closed outcomes and corrected academic evidence.
            if ($cases->latestForStudent($student->studentId) !== null) {
                continue;
            }
            $entries = [];
            foreach ($offerings as $offering) {
                $entries[] = ['offering' => $offering, 'mode' => RegistrationMode::Regular,
                    'class' => $program->curriculumEntries()->where('course_id', $offering->course_id)->firstOrFail()->category === CourseCategory::ProgramCore
                        ? EnrollmentClassification::Core : EnrollmentClassification::InternalElective, 'status' => CourseStatus::Regular, 'ch' => 3];
            }
            $this->enrollViaApprovedRequest($student->studentId, $advisor->id, $semester, $entries);
            foreach ($offerings as $position => $offering) {
                $sheet = $this->finalizedSheet($offering, GradeSheetKind::Initial, $instructor, $head, 4);
                $this->grade($sheet, $this->enrollmentId($student, $offering), $position === 0 ? 62 : 45);
            }
            if ($cases->reconcile($student->studentId) === null) {
                throw new \RuntimeException('Worked D/F Report evidence did not produce a Termination Case.');
            }
        }
    }
}
