<?php

namespace Modules\Admissions\Public;

interface ApplicationPipelines
{
    /** Live actor scope; no reads write timeline or lifecycle state. */
    public function summarize(int $actorUserId, int $admissionCycleId, ?int $programId = null, ?string $degreeLevel = null): ApplicationPipelineSummary;
}
