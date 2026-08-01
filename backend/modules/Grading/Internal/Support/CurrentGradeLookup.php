<?php

namespace Modules\Grading\Internal\Support;

use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Http\Controllers\GradeSheetController;
use Modules\Grading\Internal\Models\Grade;

/**
 * The **current** Grade for a set of Enrollments (ADR-0023): "nothing is visible until the
 * Department Head has finalized the Sheet" (ticket 04, user story 40), so only a Grade on a
 * **Finalized** Sheet is ever returned — a Draft/Submitted mark already saved by
 * {@see GradeSheetController::saveLine()} is not yet
 * part of the academic record.
 *
 * An Enrollment can in principle carry more than one Finalized Grade over time — an
 * `IncompleteResolution` or `Correction` round (tickets 05/06) finalizes a fresh row for the
 * same Enrollment rather than editing the old one (ADR-0023: "the current Grade for an
 * Enrollment is the one on its latest Finalized Sheet, and the earlier one remains visible as
 * history") — so this always keeps the one whose Sheet was reviewed most recently, never the
 * first one found.
 */
final class CurrentGradeLookup
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, Grade> keyed by enrollment id; ids with no Finalized Grade are absent
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
            ->unique('enrollment_id')
            ->keyBy(fn (Grade $grade): int => (int) $grade->enrollment_id)
            ->all();
    }
}
