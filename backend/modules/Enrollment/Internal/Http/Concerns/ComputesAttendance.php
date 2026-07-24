<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Support\AttendanceCalculator;

/**
 * The derived-attendance reads shared by the instructor register and the student's My
 * Schedule (ADR-0019, ticket 08). A Student's attendance percentage is never stored: it is
 * tallied on demand from the present/absent marks on **non-cancelled** sessions (a cancelled
 * session counts in no denominator). One batched query keyed by enrollment id feeds every
 * surface, so a roster or a schedule costs one read, not one per row.
 *
 * The audit-at-risk decision lives here too: an Audit Enrollment below the config threshold
 * is flagged (a chip + the notification the AttendanceController fires on the downward
 * crossing). The threshold is the one place Appendix D's ≥75% rule is encoded.
 */
trait ComputesAttendance
{
    /**
     * Present/recorded tallies for the given Enrollments, keyed by enrollment id, over
     * **non-cancelled** sessions only. Enrollments with no marks are simply absent from the
     * result (the caller treats a missing id as 0/0 → "no data yet").
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, array{present: int, recorded: int}>
     */
    protected function attendanceTallies(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $rows = DB::table('attendance_marks')
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_marks.class_session_id')
            ->whereNull('class_sessions.cancelled_at')
            ->whereIn('attendance_marks.enrollment_id', $enrollmentIds)
            ->groupBy('attendance_marks.enrollment_id')
            ->selectRaw('attendance_marks.enrollment_id as enrollment_id')
            ->selectRaw('count(*) as recorded')
            ->selectRaw('count(*) filter (where attendance_marks.present) as present')
            ->get();

        $tallies = [];
        foreach ($rows as $row) {
            $tallies[(int) $row->enrollment_id] = [
                'present' => (int) $row->present,
                'recorded' => (int) $row->recorded,
            ];
        }

        return $tallies;
    }

    /** The audit-at-risk attendance threshold (Appendix D's ≥75%), config-driven. */
    protected function auditThreshold(): float
    {
        return (float) config('enrollment.attendance.audit_min_percent');
    }

    /**
     * The wire shape of one Enrollment's attendance — delegates to the single-source
     * {@see AttendanceCalculator::block()} with this surface's config threshold.
     *
     * @return array{present: int, recorded: int, percentage: int, at_risk: bool}
     */
    protected function attendanceArray(int $present, int $recorded, bool $isAudit): array
    {
        return AttendanceCalculator::block($present, $recorded, $isAudit, $this->auditThreshold());
    }

    /**
     * Stash the derived `attendance` array on each Enrollment (read by the
     * `EnrollmentResource`), so the student's My Schedule shows their percentage and
     * audit-at-risk chip. One batched tally read.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     */
    protected function attachAttendance(Collection $enrollments): void
    {
        $ids = $enrollments->map(fn (Enrollment $enrollment): int => (int) $enrollment->getKey())->all();
        $tallies = $this->attendanceTallies($ids);

        $enrollments->each(function (Enrollment $enrollment) use ($tallies): void {
            $tally = $tallies[(int) $enrollment->getKey()] ?? ['present' => 0, 'recorded' => 0];
            $enrollment->setAttribute('attendance', $this->attendanceArray(
                $tally['present'],
                $tally['recorded'],
                $enrollment->course_status === CourseStatus::Audit,
            ));
        });
    }
}
