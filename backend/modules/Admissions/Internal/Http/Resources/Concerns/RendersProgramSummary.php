<?php

namespace Modules\Admissions\Internal\Http\Resources\Concerns;

use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Catalog\Public\ProgramSummary;

/**
 * Flattens a Catalog {@see ProgramSummary} DTO into the flat, bilingual array the
 * SPA renders — shared by every Admissions resource that surfaces a chosen Program
 * ({@see ApplicationResource},
 * {@see ApplicationQueueResource}), so the
 * wire shape lives in one place. A null (unset or dangling reference — no FK
 * guarantees existence, ADR-0012) flattens to null.
 */
trait RendersProgramSummary
{
    /** @return array<string, mixed>|null */
    protected function programSummaryArray(?ProgramSummary $program): ?array
    {
        if ($program === null) {
            return null;
        }

        return [
            'id' => $program->id,
            'code' => $program->code,
            'name_en' => $program->nameEn,
            'name_ar' => $program->nameAr,
            'degree_level' => $program->degreeLevel->value,
            'department_name_en' => $program->departmentNameEn,
            'department_name_ar' => $program->departmentNameAr,
            'faculty_name_en' => $program->facultyNameEn,
            'faculty_name_ar' => $program->facultyNameAr,
        ];
    }
}
