<?php

namespace Modules\Enrollment\Internal\Enums;

use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Support\EligibilityClassifier;

/**
 * How one Enrollment counts for the Student's Program — the five-way Enrollment
 * Classification (CONTEXT.md, BR-14…26, ADR-0017). Distinct from Catalog's
 * {@see CourseCategory} (the Course's per-Program
 * placement in a Curriculum): a Classification is per-Student and per-Enrollment,
 * derived at registration time from the Student's Curriculum, the Course level, and
 * the degree level.
 *
 * {@see EligibilityClassifier} is the single authority that derives it; a later ticket
 * snapshots the chosen case onto the minted Enrollment so a curriculum edit never
 * re-classifies a past registration (ADR-0017).
 *
 * Enrollment-owned and Internal, like {@see Weekday}: the value crosses to the SPA as
 * its plain string (the eligible-list resource groups on it), so no cross-module
 * consumer references this type by name (ADR-0008 closed-set-in-code precedent).
 */
enum EnrollmentClassification: string
{
    /** A required Preparatory Course in the Student's own Curriculum (BR-14). */
    case Preparatory = 'Preparatory';

    /** A Program Core Course in the Student's own Curriculum (BR-15). */
    case Core = 'Core';

    /**
     * A Research Elective from the Student's own Curriculum at the degree's elective
     * level — 500 for MSc, 600 for PhD (BR-16, BR-23, BR-26).
     */
    case InternalElective = 'InternalElective';

    /**
     * An elective at the degree's level taken from another active graduate Program's
     * Curriculum — outside the Student's own (BR-24, BR-26). One allowance per Student.
     */
    case ExternalElective = 'ExternalElective';

    /**
     * The level-700 Research Course from the Student's own Curriculum whose Research
     * Type matches the degree — PBL for MSc, Seminar for PhD (BR-25). Exactly one.
     */
    case ResearchCourse = 'ResearchCourse';
}
