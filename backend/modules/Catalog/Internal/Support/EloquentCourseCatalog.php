<?php

namespace Modules\Catalog\Internal\Support;

use Modules\Catalog\CatalogServiceProvider;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;

/**
 * The Eloquent-backed {@see CourseCatalog}. It reads Catalog's own {@see Course}
 * model and maps each to a {@see CourseSummary} DTO, so the model itself never
 * crosses the seam (ADR-0012). Bound to the interface in {@see CatalogServiceProvider}.
 *
 * The Research Type is mapped to its plain enum value (or `null`) so the DTO carries
 * no Catalog-internal type across the module boundary.
 */
class EloquentCourseCatalog implements CourseCatalog
{
    public function find(int $id): ?CourseSummary
    {
        $course = Course::query()->find($id);

        return $course === null ? null : $this->toSummary($course);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, CourseSummary>
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Course::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Course $course): array => [$course->getKey() => $this->toSummary($course)])
            ->all();
    }

    private function toSummary(Course $course): CourseSummary
    {
        return new CourseSummary(
            id: $course->getKey(),
            code: $course->code,
            nameEn: $course->name_en,
            nameAr: $course->name_ar,
            level: $course->level,
            totalCh: $course->total_ch,
            researchType: $course->research_type?->value,
            isActive: $course->is_active,
        );
    }
}
