<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Identity\Public\UserSummary;

/**
 * A read-only view of a Course for consumers outside Catalog (ADR-0012). This is the
 * *only* shape in which Course data crosses the module seam: the Eloquent
 * {@see Course} model never leaves `Catalog\Internal`,
 * so a consumer gets exactly these fields — id, code, both names, level, total credit
 * hours, research type, and the active flag — and none of Catalog's write surface.
 *
 * Enrollment is the first consumer (ticket 03): a Course Offering stores its Course's
 * id as an opaque reference (no cross-module foreign key) and resolves these display
 * and accounting facts through {@see CourseCatalog}. Later Phase-3 tickets read the
 * same DTO to classify an eligible course (level + research type) and to snapshot a
 * minted Enrollment's total CH (ADR-0017).
 *
 * {@see $researchType} is exposed as the enum's plain string value (`'PBL'`,
 * `'Seminar'`, or `null`) rather than the {@see ResearchType}
 * enum, which stays Catalog-internal — so reading it never drags an Internal type
 * across the seam. An immutable value object — the facade builds it, callers only read it.
 *
 * {@see $departmentId} is the opaque, nullable Department reference (ADR-0012):
 * NULL is the shared, university-wide Preparatory scope (ADR-0010). Grading is the
 * first consumer to read it (ticket 03) — routing a signed Grade Sheet to its
 * Department Head is "the Sheet's Offering's Course's Department", matched against
 * `Identity\Public`'s DepartmentHeadAssignments with no special case for NULL.
 */
final readonly class CourseSummary
{
    public function __construct(
        public int $id,
        public string $code,
        public string $nameEn,
        public string $nameAr,
        public CourseLevel $level,
        public int $totalCh,
        public ?string $researchType,
        public bool $isActive,
        public ?int $departmentId,
    ) {}

    /**
     * The public array shape for JSON responses — the single home for how a Course
     * summary is exposed across the seam (mirroring {@see UserSummary::toArray()}),
     * so every screen that embeds a Course (the offerings list, later the eligible
     * list) renders it identically. The level is the numeric enum value.
     *
     * @return array{id: int, code: string, name_en: string, name_ar: string, level: int, total_ch: int, research_type: string|null, is_active: bool, department_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->nameEn,
            'name_ar' => $this->nameAr,
            'level' => $this->level->value,
            'total_ch' => $this->totalCh,
            'research_type' => $this->researchType,
            'is_active' => $this->isActive,
            'department_id' => $this->departmentId,
        ];
    }
}
