<?php

namespace Modules\Catalog\Internal\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Catalog\CatalogServiceProvider;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramCurriculumVersion;
use Modules\Catalog\Public\ProgramSummary;

/**
 * The Eloquent-backed {@see ProgramCatalog}. It reads Catalog's own
 * {@see Program} model (with its Department and Faculty) and maps each to a
 * {@see ProgramSummary} DTO, so the model itself never crosses the seam
 * (ADR-0012). Bound to the interface in {@see CatalogServiceProvider}.
 */
class EloquentProgramCatalog implements ProgramCatalog
{
    public function find(int $id): ?ProgramSummary
    {
        $program = Program::query()
            ->with('department.faculty')
            ->find($id);

        return $program === null ? null : $this->toSummary($program);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, ProgramSummary>
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Program::query()
            ->with('department.faculty')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Program $program): array => [$program->getKey() => $this->toSummary($program)])
            ->all();
    }

    public function curriculumVersionEffectiveAt(int $programId, CarbonInterface $at): ?ProgramCurriculumVersion
    {
        $version = CatalogVersion::query()
            ->where('subject_type', VersionSubject::Program->value)
            ->where('subject_id', $programId)
            ->where('changed_at', '<=', $at)
            ->orderByDesc('changed_at')
            ->orderByDesc('version_no')
            ->first();

        return $this->toCurriculumVersion($version);
    }

    public function earliestCurriculumVersion(int $programId): ?ProgramCurriculumVersion
    {
        $version = CatalogVersion::query()
            ->where('subject_type', VersionSubject::Program->value)
            ->where('subject_id', $programId)
            ->orderBy('changed_at')
            ->orderBy('version_no')
            ->first();

        return $this->toCurriculumVersion($version);
    }

    private function toCurriculumVersion(?CatalogVersion $version): ?ProgramCurriculumVersion
    {
        if ($version === null) {
            return null;
        }

        return new ProgramCurriculumVersion(
            id: (int) $version->getKey(),
            programId: $version->subject_id,
            versionNo: $version->version_no,
            changedAt: CarbonImmutable::instance($version->changed_at),
        );
    }

    private function toSummary(Program $program): ProgramSummary
    {
        $department = $program->department;
        $faculty = $department->faculty;

        return new ProgramSummary(
            id: $program->getKey(),
            code: $program->code,
            nameEn: $program->name_en,
            nameAr: $program->name_ar,
            degreeLevel: $program->degree_level,
            departmentId: $department->getKey(),
            facultyId: $faculty->getKey(),
            departmentNameEn: $department->name_en,
            departmentNameAr: $department->name_ar,
            facultyNameEn: $faculty->name_en,
            facultyNameAr: $faculty->name_ar,
        );
    }
}
