<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\SemesterSummary;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Models\Grade;

/**
 * The whole result of tallying one Student's CGPA-counting Enrollments (ADR-0020, ticket 04) —
 * the single fetch {@see StudentGpaTally::recordFor()} performs, so a request never queries
 * the roster, the Grade table or the Calendar facade twice: `Grading\Public`'s facade needs
 * only {@see $attempts} for its arithmetic, while the My Grades controller additionally needs
 * {@see $enrollments}, {@see $grades} and {@see $semesters} to render the display Grading\Public
 * never exposes (course/credit-hours/superseded per row, grouped by Semester).
 */
final readonly class StudentGpaRecord
{
    /**
     * @param  list<EnrollmentSummary>  $enrollments  the Student's CGPA-counting Enrollments,
     *                                                graded or not yet
     * @param  array<int, Grade>  $grades  the current Grade per Enrollment id (ADR-0023's latest
     *                                     Finalized Sheet), keyed by id; ids with none are absent
     * @param  array<int, SemesterSummary>  $semesters  every Semester among the *graded* subset
     *                                                  of $enrollments, keyed by id
     * @param  list<GpaAttempt>  $attempts  the graded subset of $enrollments as arithmetic
     *                                      input, unfiltered by supersession
     */
    public function __construct(
        public array $enrollments,
        public array $grades,
        public array $semesters,
        public array $attempts,
    ) {}
}
