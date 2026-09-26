<?php

namespace Modules\Enrollment\Internal\Support;

/** Renders structured manual Exemption evidence for a renderer-less export. */
final class ManualExemptionEvidencePresenter
{
    /** @param array<string, mixed> $evidence */
    public function summary(array $evidence): string
    {
        if (isset($evidence['english_test'], $evidence['english_score'])) {
            return __('credit_decisions.evidence.english', [
                'test' => (string) $evidence['english_test'],
                'score' => (string) $evidence['english_score'],
            ]);
        }

        $degree = (string) ($evidence['previous_degree'] ?? '');
        $programId = isset($evidence['previous_ejust_program_id'])
            ? (string) $evidence['previous_ejust_program_id']
            : null;

        return $programId === null
            ? __('credit_decisions.evidence.ejust_degree', ['degree' => $degree])
            : __('credit_decisions.evidence.ejust_program_degree', ['degree' => $degree, 'program' => $programId]);
    }
}
