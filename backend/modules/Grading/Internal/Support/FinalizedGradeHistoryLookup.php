<?php

namespace Modules\Grading\Internal\Support;

use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;

/**
 * The finalized Grade history for a set of Enrollments (phase 4 ticket 06). Correction rounds
 * add a fresh Grade on the same Enrollment instead of editing the earlier one, so both the
 * Student's record and the oversight views need the ordered finalized history — newest first —
 * to show "current plus superseded" without a stored superseded flag (ADR-0023).
 */
final class FinalizedGradeHistoryLookup
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, list<Grade>> keyed by enrollment id; ids with no finalized Grade are absent
     */
    public static function forEnrollments(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        return Grade::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereHas('sheet', fn ($query) => $query->where('status', GradeSheetStatus::Finalized))
            ->with('sheet')
            ->get()
            ->sortByDesc(fn (Grade $grade): array => [
                $grade->sheet->reviewed_at?->getTimestamp() ?? 0,
                (int) $grade->sheet->getKey(),
                (int) $grade->getKey(),
            ])
            ->groupBy(fn (Grade $grade): int => (int) $grade->enrollment_id)
            ->map(fn ($grades): array => array_values($grades->all()))
            ->all();
    }
}
