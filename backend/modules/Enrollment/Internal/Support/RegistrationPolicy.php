<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Identity\Public\StudentSummary;

final readonly class RegistrationPolicy
{
    public function __construct(private CurriculumCatalog $curricula, private AcademicCalendar $calendar) {}

    public function ceiling(int $studentId): int
    {
        return (int) (DB::table('registration_policy_projections')->where('student_id', $studentId)->value('registration_ceiling_ch')
            ?? config('enrollment.max_credit_hours'));
    }

    /** @param list<CartLine> $lines */
    public function exceptions(StudentSummary $student, string $semesterName, array $lines): CreditHoursExceptions
    {
        $load = array_sum(array_map(fn (CartLine $line): int => $line->mode === RegistrationMode::Audit ? 0 : $line->totalCh, $lines));
        if ($load < 1 || $load > 5) {
            return new CreditHoursExceptions;
        }

        $requirements = $this->curricula->requirementsForVersion($student->curriculumVersionId);
        if ($requirements === null) {
            return new CreditHoursExceptions(firstSemester: $student->enrollmentTerm === $semesterName);
        }

        $entries = $this->curricula->entriesForVersion($student->curriculumVersionId);
        $categories = [];
        foreach ($entries as $entry) {
            $categories[$entry->courseId] = $entry->category;
        }
        $satisfied = $this->satisfiedCourseIds($student->studentId);
        $remainingPreparatory = [];
        $remainingExact = [];
        foreach ($entries as $entry) {
            if (isset($satisfied[$entry->courseId]) || $entry->category === CourseCategory::ResearchElective) {
                continue;
            }
            $remainingExact[] = $entry->courseId;
            if ($entry->category === CourseCategory::Preparatory) {
                $remainingPreparatory[] = $entry->courseId;
            }
        }

        $selected = array_fill_keys(array_map(fn (CartLine $line): int => $line->courseId, array_filter($lines, fn (CartLine $line): bool => $line->mode !== RegistrationMode::Audit)), true);
        $contains = static fn (array $targets): bool => array_diff($targets, array_keys($selected)) === [];
        $remainingByCategory = [CourseCategory::Preparatory->value => $requirements->preparatoryCh, CourseCategory::ProgramCore->value => $requirements->programCoreCh, CourseCategory::ResearchElective->value => $requirements->researchElectiveCh];
        foreach ($satisfied as $courseId => $_) {
            $category = $categories[$courseId] ?? null;
            if ($category !== null) {
                $course = collect($entries)->first(fn ($entry): bool => $entry->courseId === $courseId);
                $remainingByCategory[$category->value] = max(0, $remainingByCategory[$category->value] - $course->totalCh);
            }
        }
        $totalRemaining = array_sum($remainingByCategory);
        $selectedElective = array_sum(array_map(fn (CartLine $line): int => $line->classification === EnrollmentClassification::ResearchCourse || $line->classification === EnrollmentClassification::InternalElective || $line->classification === EnrollmentClassification::ExternalElective ? $line->totalCh : 0, array_filter($lines, fn (CartLine $line): bool => $line->mode !== RegistrationMode::Audit)));

        return new CreditHoursExceptions(
            firstSemester: $student->enrollmentTerm === $semesterName && $remainingPreparatory !== [] && $contains($remainingPreparatory),
            approachingGraduationCompletion: $totalRemaining >= 1 && $totalRemaining <= 5 && $contains($remainingExact) && $selectedElective >= $remainingByCategory[CourseCategory::ResearchElective->value],
        );
    }

    /** @return array<int, true> */
    private function satisfiedCourseIds(int $studentId): array
    {
        $latest = [];
        foreach (Enrollment::query()->where('student_id', $studentId)->whereNotNull('earned')->with('offering')->get() as $enrollment) {
            $offering = $enrollment->offering;
            $semester = $offering === null ? null : $this->calendar->find((int) $offering->semester_id);
            if ($offering === null || $semester === null) {
                continue;
            }
            $courseId = (int) $offering->course_id;
            $candidate = ['enrollment' => $enrollment, 'starts_at' => $semester->startsAt];
            $current = $latest[$courseId] ?? null;
            if ($current === null || $candidate['starts_at']->greaterThan($current['starts_at']) || ($candidate['starts_at']->equalTo($current['starts_at']) && $enrollment->id > $current['enrollment']->id)) {
                $latest[$courseId] = $candidate;
            }
        }
        $ids = [];
        foreach ($latest as $courseId => $attempt) {
            if ($attempt['enrollment']->earned === true) {
                $ids[] = $courseId;
            }
        }
        foreach ([ExemptionDecision::class, TransferDecision::class, WaiverDecision::class] as $model) {
            $ids = [...$ids, ...$model::query()->where('student_id', $studentId)->whereDoesntHave('revocation')->pluck('target_course_id')->map(fn ($id): int => (int) $id)->all()];
        }

        return array_fill_keys(array_unique($ids), true);
    }
}
