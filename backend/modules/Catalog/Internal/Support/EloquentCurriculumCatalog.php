<?php

namespace Modules\Catalog\Internal\Support;

use Modules\Catalog\CatalogServiceProvider;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\CurriculumEntrySummary;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

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
                    preparatoryRequirementRole: $entry->preparatory_requirement_role,
                    ordinal: $entry->ordinal,
                ),
            ])
            ->all();
    }

    /** @return list<BoundCurriculumEntrySummary> */
    public function entriesForVersion(int $curriculumVersionId): array
    {
        $version = CatalogVersion::query()->find($curriculumVersionId);
        if ($version === null || $version->subject_type !== VersionSubject::Program) {
            return [];
        }

        $curriculum = $version->snapshot['curriculum'] ?? [];
        if (! is_array($curriculum)) {
            return [];
        }

        $entries = [];
        foreach ($curriculum as $row) {
            if (! is_array($row)) {
                continue;
            }

            $category = CourseCategory::tryFrom((string) ($row['category'] ?? ''));
            if ($category === null) {
                continue;
            }

            $roleValue = $row['preparatory_requirement_role'] ?? null;
            $entries[] = new BoundCurriculumEntrySummary(
                curriculumVersionId: (int) $version->getKey(),
                programId: $version->subject_id,
                courseId: (int) ($row['course_id'] ?? 0),
                courseCode: (string) ($row['course_code'] ?? ''),
                // Preparatory Courses are fixed at 1 CH; snapshots recorded before
                // this field was introduced therefore remain deterministic.
                totalCh: (int) ($row['course_total_ch'] ?? ($category === CourseCategory::Preparatory ? 1 : 0)),
                category: $category,
                preparatoryRequirementRole: is_string($roleValue)
                    ? PreparatoryRequirementRole::tryFrom($roleValue)
                    : null,
                ordinal: (int) ($row['ordinal'] ?? 0),
            );
        }

        return $entries;
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
