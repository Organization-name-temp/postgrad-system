<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Http\Controllers\MyGradesController;

/**
 * A Student's whole CGPA/GPA input, assembled from `Enrollment\Public`'s roster,
 * `Grading\Internal`'s own Grades, and `Calendar\Public`'s Semester dates (ADR-0020, ADR-0022,
 * ticket 04) — the one place that assembly happens, in **one fetch each** of the roster, the
 * Grade table and the Calendar facade. {@see self::recordFor()} returns the whole
 * {@see StudentGpaRecord}, shared by {@see EloquentGradingRecords} (the cross-module facade,
 * which reads only its `attempts`) and the My Grades controller (which additionally reads
 * `enrollments`/`grades`/`semesters` for the richer student-facing view) — neither re-queries
 * what the other already fetched, and neither re-derives "which of this Student's Enrollments
 * are CGPA-counting and graded".
 *
 * Filters to `Enrollment\Public`'s own {@see EnrollmentSummary::$countsTowardCgpa} judgement
 * (Appendix D, read never reimplemented) — **plus** any `Incomplete` or `FailWithdrawal`
 * Enrollment (ticket 05), neither of which counts toward the CGPA but both of which must still
 * reach the Student's own My Grades view with their Course Status (an Incomplete's derived
 * deadline, and — after conversion — the Fail Withdrawal itself). Comparing against those two
 * statuses' plain string values rather than `Enrollment\Internal\Enums\CourseStatus` is
 * deliberate: that enum stays Internal (ADR-0012), so Grading names only the two values it
 * cares about, never the type. `attempts` for the arithmetic still comes from Enrollments with
 * a **Finalized** Grade ({@see CurrentGradeLookup}) only — an Incomplete/FailWithdrawal
 * Enrollment never has one, so it can never contaminate the CGPA. `attempts` is **unfiltered
 * by supersession**: {@see GpaCalculator::nonSuperseded()} derives that at read time, never
 * here.
 */
final readonly class StudentGpaTally
{
    /** Course Statuses shown on My Grades though they never count toward the CGPA (ticket 05). */
    private const UNGRADED_VISIBLE_STATUSES = ['Incomplete', 'FailWithdrawal'];

    public function __construct(
        private EnrollmentRecords $enrollments,
        private AcademicCalendar $calendar,
    ) {}

    /**
     * Whether an Enrollment in this Course Status belongs on My Grades despite never carrying
     * a Grade — the one membership test {@see self::recordFor()} and
     * {@see MyGradesController} both need, so the
     * two-value set lives in exactly one place.
     */
    public static function isUngradedVisible(string $courseStatus): bool
    {
        return in_array($courseStatus, self::UNGRADED_VISIBLE_STATUSES, true);
    }

    public function recordFor(int $studentId): StudentGpaRecord
    {
        $relevant = array_values(array_filter(
            $this->enrollments->enrollmentsForStudent($studentId),
            fn (EnrollmentSummary $enrollment): bool => $enrollment->countsTowardCgpa
                || self::isUngradedVisible($enrollment->courseStatus),
        ));

        if ($relevant === []) {
            return new StudentGpaRecord(enrollments: [], grades: [], semesters: [], attempts: []);
        }

        $grades = CurrentGradeLookup::forEnrollments(array_map(
            fn (EnrollmentSummary $enrollment): int => $enrollment->id,
            $relevant,
        ));

        // Every Semester among the relevant set, not only the graded subset — an Incomplete or
        // Fail Withdrawal never carries a Grade but still needs its Semester resolved for My
        // Grades' section grouping and (for an Incomplete) the deadline calculation.
        $semesters = [];
        foreach (array_unique(array_map(fn (EnrollmentSummary $e): int => $e->semesterId, $relevant)) as $semesterId) {
            $semester = $this->calendar->find($semesterId);
            if ($semester !== null) {
                $semesters[$semesterId] = $semester;
            }
        }

        // The arithmetic input stays exactly what it always was — CGPA-counting AND graded —
        // so an Incomplete/Fail-Withdrawal row (never graded) can never reach it even though it
        // now rides along in $relevant/$semesters for My Grades' display.
        $graded = array_values(array_filter(
            $relevant,
            fn (EnrollmentSummary $e): bool => $e->countsTowardCgpa && isset($grades[$e->id]),
        ));

        $attempts = [];
        foreach ($graded as $enrollment) {
            $semester = $semesters[$enrollment->semesterId] ?? null;
            if ($semester === null) {
                continue;
            }

            $attempts[] = new GpaAttempt(
                enrollmentId: $enrollment->id,
                courseId: $enrollment->courseId,
                semesterId: $enrollment->semesterId,
                semesterStartsAt: $semester->startsAt,
                creditHours: $enrollment->creditHours,
                gpaPoints: (float) $grades[$enrollment->id]->gpa_points,
                earned: $enrollment->earned,
            );
        }

        return new StudentGpaRecord(enrollments: $relevant, grades: $grades, semesters: $semesters, attempts: $attempts);
    }
}
