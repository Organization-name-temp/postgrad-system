<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;

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
 * (Appendix D, read never reimplemented) and to Enrollments with a **Finalized** Grade
 * ({@see CurrentGradeLookup}) for `attempts` — every gradable Course Status counts toward the
 * CGPA (spec), so this is exactly the roster
 * `Modules\Grading\Internal\Http\Controllers\GradeSheetController` would have graded.
 * `attempts` is **unfiltered by supersession**: {@see GpaCalculator::nonSuperseded()} derives
 * that at read time, never here.
 */
final readonly class StudentGpaTally
{
    public function __construct(
        private EnrollmentRecords $enrollments,
        private AcademicCalendar $calendar,
    ) {}

    public function recordFor(int $studentId): StudentGpaRecord
    {
        $counting = array_values(array_filter(
            $this->enrollments->enrollmentsForStudent($studentId),
            fn (EnrollmentSummary $enrollment): bool => $enrollment->countsTowardCgpa,
        ));

        if ($counting === []) {
            return new StudentGpaRecord(enrollments: [], grades: [], semesters: [], attempts: []);
        }

        $grades = CurrentGradeLookup::forEnrollments(array_map(
            fn (EnrollmentSummary $enrollment): int => $enrollment->id,
            $counting,
        ));

        // Only the graded subset ever needs a Semester resolved — an ungraded (not yet
        // finalized) counting Enrollment is invisible everywhere until it is.
        $graded = array_values(array_filter($counting, fn (EnrollmentSummary $e): bool => isset($grades[$e->id])));

        $semesters = [];
        foreach (array_unique(array_map(fn (EnrollmentSummary $e): int => $e->semesterId, $graded)) as $semesterId) {
            $semester = $this->calendar->find($semesterId);
            if ($semester !== null) {
                $semesters[$semesterId] = $semester;
            }
        }

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
            );
        }

        return new StudentGpaRecord(enrollments: $counting, grades: $grades, semesters: $semesters, attempts: $attempts);
    }
}
