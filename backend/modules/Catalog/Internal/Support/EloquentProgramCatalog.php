<?php

namespace Modules\Catalog\Internal\Support;

use Modules\Catalog\CatalogServiceProvider;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\ProgramCatalog;
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
