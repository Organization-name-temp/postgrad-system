<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * The outcome of {@see AttemptDeriver} for one about-to-be-minted line: the
 * {@see CourseStatus} the Enrollment would carry (Regular / Retake / CGPA Improvement /
 * Audit), and whether minting it is **blocked** because the Student already used the one
 * combined re-registration allowance for that Course (FR-REG-006). When blocked, the caller
 * raises a violation and mints nothing; otherwise it mints with {@see $status}. An immutable
 * value object.
 */
final readonly class AttemptDerivation
{
    public function __construct(
        public CourseStatus $status,
        public bool $blocked,
    ) {}
}
