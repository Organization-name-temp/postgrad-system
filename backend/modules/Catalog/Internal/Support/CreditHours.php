<?php

namespace Modules\Catalog\Internal\Support;

/**
 * A Course's credit-hour breakdown — the Lecture / Tutorial / Lab split and the
 * declared Total. The domain invariant is that the three components sum to the
 * Total (FR-PCM-003.2, ADR-0010); {@see self::isBalanced()} is the single place
 * that truth is expressed, consumed by the CourseController's validation so an
 * unbalanced breakdown is a 422 rather than silently persisted.
 *
 * Kept as a small immutable value object (not folded into the Course model) so the
 * invariant can be unit-tested directly, without the HTTP stack or the database.
 */
final readonly class CreditHours
{
    public function __construct(
        public int $lecture,
        public int $tutorial,
        public int $lab,
        public int $total,
    ) {}

    /** The sum of the three components, independent of the declared total. */
    public function componentSum(): int
    {
        return $this->lecture + $this->tutorial + $this->lab;
    }

    /** True when the components account for exactly the declared total. */
    public function isBalanced(): bool
    {
        return $this->componentSum() === $this->total;
    }
}
