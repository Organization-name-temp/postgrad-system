<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;

/**
 * The pure Classifier that places one candidate Course into the Student's five-way
 * Enrollment Classification, or reports it not eligible (BR-14…26, spec §Rules
 * engines). It is the single home for the degree/level/research-type matrix and the
 * internal-vs-external boundary — the eligible-list endpoint (ticket 04) and the mint
 * (ticket 06) both read it, so the rule is expressed once.
 *
 * Kept as a small pure value object (the {@see CreditHours}/{@see MeetingTime}
 * precedent) so the whole matrix is unit-testable without the HTTP stack or the
 * database. It touches no Catalog storage: the caller resolves the two Catalog facts —
 * the Course's Category in the Student's *own* Curriculum (or `null` if it is not in
 * it) and whether the Course sits in *another* active graduate Program's Curriculum —
 * through the {@see CurriculumCatalog} facade and hands them in.
 *
 * Retake is deliberately absent: it needs existing Enrollments and is derived at
 * registration, not here (ticket 06).
 */
final readonly class EligibilityClassifier
{
    /**
     * Classify one candidate Course for a Student, or return `null` when the Course is
     * not eligible for this Student (wrong level for the degree, a 700 slot whose
     * research type does not match, or a course in neither the own nor another active
     * graduate Curriculum).
     *
     * @param  DegreeLevel  $degree  the Student's Program degree level (MSc/PhD)
     * @param  CourseLevel  $courseLevel  the candidate Course's level
     * @param  string|null  $researchType  the Course's research type as its plain value
     *                                     ('PBL' / 'Seminar' / null), as it crosses the
     *                                     Catalog seam on a CourseSummary
     * @param  CourseCategory|null  $ownCategory  the Course's Category in the Student's
     *                                            own Curriculum, or null if not in it
     * @param  bool  $inOtherActiveGraduateCurriculum  whether the Course sits in another
     *                                                 active graduate Program's Curriculum
     */
    public function classify(
        DegreeLevel $degree,
        CourseLevel $courseLevel,
        ?string $researchType,
        ?CourseCategory $ownCategory,
        bool $inOtherActiveGraduateCurriculum,
    ): ?EnrollmentClassification {
        // In the Student's own Curriculum: the Category decides, with the Research
        // Elective category splitting by level into Internal Elective vs Research Course.
        if ($ownCategory !== null) {
            return match ($ownCategory) {
                CourseCategory::Preparatory => EnrollmentClassification::Preparatory,
                CourseCategory::ProgramCore => EnrollmentClassification::Core,
                CourseCategory::ResearchElective => $this->classifyOwnResearchElective($degree, $courseLevel, $researchType),
            };
        }

        // Outside the own Curriculum: the one external allowance — an elective-level
        // course drawn from another active graduate Program's Curriculum (BR-24, BR-26).
        if ($inOtherActiveGraduateCurriculum && $courseLevel === $this->electiveLevel($degree)) {
            return EnrollmentClassification::ExternalElective;
        }

        return null;
    }

    /**
     * Split an own-curriculum Research Elective by level: the degree's elective level
     * (500 MSc / 600 PhD) is an Internal Elective; a level-700 course whose Research
     * Type matches the degree (PBL MSc / Seminar PhD) is the Research Course; anything
     * else is not eligible.
     */
    private function classifyOwnResearchElective(
        DegreeLevel $degree,
        CourseLevel $courseLevel,
        ?string $researchType,
    ): ?EnrollmentClassification {
        if ($courseLevel === $this->electiveLevel($degree)) {
            return EnrollmentClassification::InternalElective;
        }

        if ($courseLevel === CourseLevel::Advanced && $researchType === $this->researchTypeFor($degree)) {
            return EnrollmentClassification::ResearchCourse;
        }

        return null;
    }

    /** The Course level a degree's electives sit at — MSc 500, PhD 600 (BR-26). */
    private function electiveLevel(DegreeLevel $degree): CourseLevel
    {
        return match ($degree) {
            DegreeLevel::MSc => CourseLevel::Master,
            DegreeLevel::PhD => CourseLevel::Doctoral,
        };
    }

    /**
     * The Research Type a degree's level-700 Research Course must carry (BR-25). Compared
     * as the plain string the Catalog seam exposes on a CourseSummary — Catalog's own
     * ResearchType enum stays Internal, so it is matched by value here rather than by
     * type (ADR-0012).
     */
    private function researchTypeFor(DegreeLevel $degree): string
    {
        return match ($degree) {
            DegreeLevel::MSc => 'PBL',
            DegreeLevel::PhD => 'Seminar',
        };
    }
}
