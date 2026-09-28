<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;

final class AttendanceTallies
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, array{present: int, recorded: int}>
     */
    public static function forEnrollments(array $enrollmentIds): array
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
}
