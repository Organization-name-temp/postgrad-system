<?php

namespace Modules\Enrollment\Public\Events;

/**
 * Enrollment outcomes written during one Grade Sheet finalization are durable.
 * Enrollment owns this handoff contract because consumers reread Enrollment facts.
 */
final readonly class GradeOutcomesFinalized
{
    /** @param list<int> $enrollmentIds */
    public function __construct(
        public int $gradeSheetId,
        public array $enrollmentIds,
    ) {}
}
