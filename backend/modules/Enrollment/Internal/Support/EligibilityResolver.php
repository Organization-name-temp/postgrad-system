<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Collection;
use Modules\Catalog\Public\CourseSummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Identity\Public\StudentSummary;

/**
 * Resolves each of a Semester's Course Offerings into the Student's five-way Enrollment
 * Classification — the one place the eligible-list read (ticket 04), the cart submission
 * and the request view (ticket 05) share, so the "which courses may this Student take, as
 * what?" question is answered identically everywhere.
 *
 * It batches the two Catalog facade reads the pure {@see EligibilityClassifier} needs —
 * the Student's own Curriculum ({@see CurriculumCatalog::entriesForProgram()}) and which
 * *other* active graduate Programs' curricula contain each candidate Course
 * ({@see CurriculumCatalog::programsContainingCourses()}) — then feeds the classifier per
 * Offering. Offerings must already carry their resolved Course summary (the controller's
 * {@see ResolvesOfferingRefs}); an Offering
 * that classifies for no class (or whose Course reference dropped) is simply absent from
 * the result.
 */
final readonly class EligibilityResolver
{
    public function __construct(
        private CurriculumCatalog $curriculum,
        private ProgramCatalog $programs,
    ) {}

    /**
     * Classify the given Offerings for the Student, returning `offering id =>
     * classification` for the eligible ones only. Empty when the Student's Program
     * reference dangles.
     *
     * @param  Collection<int, CourseOffering>  $offerings  each with `resolved_course` attached
     * @return array<int, EnrollmentClassification>
     */
    public function classify(StudentSummary $student, Collection $offerings): array
    {
        $program = $this->programs->find($student->programId);
        if ($program === null) {
            return [];
        }

        $ownEntries = $this->curriculum->entriesForProgram($student->programId);
        $courseIds = $offerings->pluck('course_id')->filter()->unique()->values()->all();
        $programsByCourse = $this->curriculum->programsContainingCourses($courseIds);

        $classifier = new EligibilityClassifier;
        $result = [];

        foreach ($offerings as $offering) {
            $course = $offering->getAttribute('resolved_course');
            if (! $course instanceof CourseSummary) {
                continue; // a dropped Course reference (no FK) — never eligible
            }

            $ownCategory = ($ownEntries[$offering->course_id] ?? null)?->category;

            // "in another active graduate program's curriculum" — exclude the Student's own.
            $inOther = collect($programsByCourse[$offering->course_id] ?? [])
                ->contains(fn (int $programId): bool => $programId !== $student->programId);

            $classification = $classifier->classify(
                degree: $program->degreeLevel,
                courseLevel: $course->level,
                researchType: $course->researchType,
                ownCategory: $ownCategory,
                inOtherActiveGraduateCurriculum: $inOther,
            );

            if ($classification !== null) {
                $result[$offering->getKey()] = $classification;
            }
        }

        return $result;
    }
}
