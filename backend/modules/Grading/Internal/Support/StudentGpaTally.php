<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Http\Controllers\MyGradesController;
use Modules\Grading\Internal\Models\Grade;

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
        return $this->recordsFor([$studentId])[$studentId];
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, StudentGpaRecord>
     */
    public function recordsFor(array $studentIds): array
    {
        $records = [];
        foreach (array_chunk(array_values(array_unique($studentIds)), 100) as $batch) {
            $rosters = $this->enrollments->enrollmentsForStudents($batch);
            $relevantByStudent = [];
            $enrollmentIds = [];
            $semesterIds = [];
            foreach ($rosters as $studentId => $roster) {
                $relevant = array_values(array_filter($roster, fn (EnrollmentSummary $e): bool => $e->countsTowardCgpa || self::isUngradedVisible($e->courseStatus)));
                $relevantByStudent[$studentId] = $relevant;
                foreach ($relevant as $enrollment) {
                    $enrollmentIds[] = $enrollment->id;
                    $semesterIds[] = $enrollment->semesterId;
                }
            }
            $grades = [];
            foreach (array_chunk($enrollmentIds, 500) as $gradeBatch) {
                $grades += CurrentGradeLookup::forEnrollments($gradeBatch);
            }
            $semesters = [];
            foreach (array_chunk(array_values(array_unique($semesterIds)), 100) as $semesterBatch) {
                $semesters += $this->calendar->findMany($semesterBatch);
            }
            foreach ($relevantByStudent as $studentId => $relevant) {
                $ownIds = array_fill_keys(array_map(fn (EnrollmentSummary $e): int => $e->id, $relevant), true);
                $ownSemesters = array_fill_keys(array_map(fn (EnrollmentSummary $e): int => $e->semesterId, $relevant), true);
                $records[$studentId] = $this->assemble($relevant, array_intersect_key($grades, $ownIds), array_intersect_key($semesters, $ownSemesters));
            }
        }

        return $records;
    }

    /**
     * @param  list<EnrollmentSummary>  $relevant
     * @param  array<int, Grade>  $grades
     * @param  array<int, SemesterSummary>  $semesters
     */
    private function assemble(array $relevant, array $grades, array $semesters): StudentGpaRecord
    {
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
