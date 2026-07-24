<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * The outcome of {@see RetakeDeriver} for one about-to-be-minted line: the {@see CourseStatus}
 * the Enrollment would carry (Regular / Retake / Audit), and whether minting it is
 * **blocked** because it would be a second retake of the same course (FR-REG-006's cap of
 * one). When blocked, the caller raises a violation and mints nothing; otherwise it mints
 * with {@see $status}. An immutable value object.
 */
final readonly class RetakeDerivation
{
    public function __construct(
        public CourseStatus $status,
        public bool $blocked,
    ) {}
}
