<?php

namespace Modules\Catalog\Internal\Support;

use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

/** Evaluates the six-role completeness invariant from ADR-0031. */
class PreparatoryCurriculumCompleteness
{
    /** @return list<PreparatoryRequirementRole> */
    public function missingRoles(Program $program): array
    {
        $assigned = $program->curriculumEntries()
            ->where('category', CourseCategory::Preparatory->value)
            ->pluck('preparatory_requirement_role')
            ->filter()
            ->map(fn (mixed $role): string => $role instanceof PreparatoryRequirementRole ? $role->value : (string) $role)
            ->all();

        return array_values(array_filter(
            PreparatoryRequirementRole::cases(),
            fn (PreparatoryRequirementRole $role): bool => ! in_array($role->value, $assigned, true),
        ));
    }

    public function isComplete(Program $program): bool
    {
        return $this->missingRoles($program) === [];
    }
}
