<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * One approved line's plan for the Enrollment mint (ticket 06) — the resolved {@see CartLine}
 * and the {@see CourseStatus} {@see AttemptDeriver} derived for it (Regular / Retake / Audit).
 * A small immutable value object so the advisor decision carries the mint intent through its
 * re-validation to its transaction in the module's readonly-VO style, rather than an untyped
 * tuple.
 */
final readonly class MintPlanEntry
{
    public function __construct(
        public CartLine $cartLine,
        public CourseStatus $status,
    ) {}
}
