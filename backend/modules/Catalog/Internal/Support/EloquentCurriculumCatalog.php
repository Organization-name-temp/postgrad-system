<?php

namespace Modules\Catalog\Internal\Support;

use Modules\Catalog\CatalogServiceProvider;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\CurriculumEntrySummary;

/**
 * The Eloquent-backed {@see CurriculumCatalog}. It reads Catalog's own
 * {@see CurriculumEntry} model (joining `programs` for the active-graduate filter) and
 * maps each row to a {@see CurriculumEntrySummary} DTO, so the model itself never
 * crosses the seam (ADR-0012). Bound to the interface in {@see CatalogServiceProvider}.
 */
class EloquentCurriculumCatalog implements CurriculumCatalog
{
    /**
     * @return array<int, CurriculumEntrySummary>
     */
    public function entriesForProgram(int $programId): array
    {
        return CurriculumEntry::query()
            ->where('program_id', $programId)
            ->get()
            ->mapWithKeys(fn (CurriculumEntry $entry): array => [
                $entry->course_id => new CurriculumEntrySummary(
                    programId: $entry->program_id,
                    courseId: $entry->course_id,
                    category: $entry->category,
                    ordinal: $entry->ordinal,
                ),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, list<int>>
     */
    public function programsContainingCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return CurriculumEntry::query()
            ->join('programs', 'programs.id', '=', 'curriculum_entries.program_id')
            ->where('programs.is_active', true)
            ->whereIn('curriculum_entries.course_id', $courseIds)
            ->get(['curriculum_entries.course_id', 'curriculum_entries.program_id'])
            ->groupBy('course_id')
            ->map(fn ($rows): array => $rows
                ->pluck('program_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all())
            ->all();
    }
}
