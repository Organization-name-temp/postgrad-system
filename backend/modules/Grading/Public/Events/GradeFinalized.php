<?php

namespace Modules\Grading\Public\Events;

/**
 * A Grade Sheet round was finalized and its affected Enrollment outcomes are durable.
 * Consumers reread their own authoritative facts using these opaque Enrollment IDs.
 */
final readonly class GradeFinalized
{
    /** @param list<int> $enrollmentIds */
    public function __construct(
        public int $gradeSheetId,
        public array $enrollmentIds,
    ) {}
}
