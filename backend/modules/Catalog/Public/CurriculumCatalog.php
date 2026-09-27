<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\CatalogServiceProvider;

/**
 * The read facade other modules use to resolve Program Curricula by id (ADR-0012) —
 * the Curriculum-reader sibling of {@see CourseCatalog}/{@see ProgramCatalog}, the
 * long-deferred second half of the Catalog cross-module surface (ADR-0010's
 * consequence note). Callers store Program/Course ids as opaque references (no
 * cross-module foreign key) and read Curriculum placements back only through this
 * contract, which returns {@see CurriculumEntrySummary} DTOs — never Catalog's
 * Eloquent models. The concrete implementation lives in `Catalog\Internal` and is
 * bound in {@see CatalogServiceProvider}.
 *
 * Phase-3 Enrollment is the first consumer: the eligibility Classifier reads a
 * Student's own Curriculum ({@see self::entriesForProgram()}) to place a candidate
 * Course by Category, and the "which active graduate Programs contain this Course"
 * lookup ({@see self::programsContainingCourses()}) to decide the External-Elective
 * test (BR-24).
 */
interface CurriculumCatalog
{
    /** The category requirements frozen into a Program Curriculum Version. */
    public function requirementsForVersion(int $curriculumVersionId): ?CurriculumRequirementsSummary;

    /** @param list<int> $curriculumVersionIds
     * @return array<int, CurriculumRequirementsSummary>
     */
    public function requirementsForVersions(array $curriculumVersionIds): array;

    /** @param list<int> $curriculumVersionIds
     * @return array<int, list<BoundCurriculumEntrySummary>>
     */
    public function entriesForVersions(array $curriculumVersionIds): array;

    /**
     * The Curriculum Entries of the given Program, keyed by Course id — a Course's
     * placement (Category + ordinal) within that Program. Empty when the Program has no
     * Curriculum or does not exist. A Course sits at most once per Program+Category
     * (the CurriculumController's uniqueness rule); if the same Course were placed under
     * two Categories of one Program, the last read wins — an operator state the domain
     * does not intend.
     *
     * @return array<int, CurriculumEntrySummary>
     */
    public function entriesForProgram(int $programId): array;

    /**
     * The entries frozen into one immutable Program Curriculum Version. A missing
     * or non-Program version yields an empty list.
     *
     * @return list<BoundCurriculumEntrySummary>
     */
    public function entriesForVersion(int $curriculumVersionId): array;

    /**
     * For each given Course id, the ids of the **active graduate** Programs whose
     * Curriculum contains it — the lookup behind the External-Elective test (BR-24, BR-26).
     * A Course id with no such Program is absent from the result. All Programs are
     * graduate degrees (MSc/PhD, BR-13), so "graduate" needs no further filter; only
     * active Programs count, so a deactivated Program never lends an external allowance
     * (BR-18). The caller excludes the Student's own Program to answer "in *another*
     * program's curriculum".
     *
     * @param  list<int>  $courseIds
     * @return array<int, list<int>> Course id => active graduate Program ids containing it
     */
    public function programsContainingCourses(array $courseIds): array;
}
