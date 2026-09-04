<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\StudentSummary;

final class ManualExemptionEligibility
{
    public function __construct(
        private readonly ProgramCatalog $programs,
        private readonly PreparatoryExemptionRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    public function reasons(StudentSummary $student, PreparatoryRequirementRole $role, array $evidence): array
    {
        $previousProgramId = isset($evidence['previous_ejust_program_id']) ? (int) $evidence['previous_ejust_program_id'] : 0;
        $admitted = $this->programs->find($student->programId);
        $previous = $previousProgramId > 0 ? $this->programs->find($previousProgramId) : null;

        return $this->rules->reasonsForRole($role, [
            ...$evidence,
            'admitted_program_degree_level' => $admitted?->degreeLevel->value,
            'admitted_department_id' => $admitted?->departmentId,
            'previous_ejust_program_degree_level' => $previous?->degreeLevel->value,
            'previous_ejust_department_id' => $previous?->departmentId,
        ]);
    }
}
