<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\AutomaticExemptionReason;

/** The single pure authority for automatic and corrected-evidence exemption rules. */
final class PreparatoryExemptionRules
{
    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, list<string>> keyed by Preparatory Requirement Role value
     */
    public function qualifyingReasonsByRole(array $evidence): array
    {
        /** @var array<string, list<string>> $reasons */
        $reasons = [];
        $add = static function (array &$target, PreparatoryRequirementRole $role, AutomaticExemptionReason $reason): void {
            $target[$role->value] ??= [];
            if (! in_array($reason->value, $target[$role->value], true)) {
                $target[$role->value][] = $reason->value;
            }
        };

        $test = (string) ($evidence['english_test'] ?? '');
        $score = $evidence['english_score'] ?? null;
        if (is_numeric($score) && (($test === 'IELTS' && (float) $score >= 6.5)
            || ($test === 'TOEFL iBT' && (float) $score >= 79))) {
            $add($reasons, PreparatoryRequirementRole::EnglishLanguage, AutomaticExemptionReason::EnglishProficiency);
        }

        $isEjust = (bool) ($evidence['is_ejust_graduate'] ?? false);
        $previousDegree = (string) ($evidence['previous_degree'] ?? '');
        if ($isEjust && $previousDegree === 'BSc') {
            foreach ([
                PreparatoryRequirementRole::JapaneseLanguage,
                PreparatoryRequirementRole::JapaneseCulture,
                PreparatoryRequirementRole::KeySkills,
            ] as $role) {
                $add($reasons, $role, AutomaticExemptionReason::EjustBsc);
            }
        }

        if ($isEjust && $previousDegree === 'MSc' && ($evidence['previous_ejust_program_id'] ?? null) !== null
            && ($evidence['admitted_program_degree_level'] ?? null) === 'PhD'
            && ($evidence['previous_ejust_program_degree_level'] ?? null) === 'MSc'
            && (int) ($evidence['admitted_department_id'] ?? 0) > 0
            && (int) ($evidence['admitted_department_id'] ?? 0) === (int) ($evidence['previous_ejust_department_id'] ?? -1)) {
            foreach (PreparatoryRequirementRole::cases() as $role) {
                $add($reasons, $role, AutomaticExemptionReason::SameDepartmentEjustMsc);
            }
        }

        return $reasons;
    }

    /** @param array<string, mixed> $evidence
     * @return list<string>
     */
    public function reasonsForRole(PreparatoryRequirementRole $role, array $evidence): array
    {
        return $this->qualifyingReasonsByRole($evidence)[$role->value] ?? [];
    }
}
