<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * The credit-hour rule exceptions that apply to one Student's registration in one
 * Semester — the context the pure {@see CreditHoursValidator} reads to decide whether the
 * 6-CH floor is waived (spec §Rules engines, FR-REG-001).
 *
 * It is a deliberate seam: ticket 05 implements only the **first-semester** exception
 * (the target Semester is the Student's enrollment term — a reduced first load is
 * allowed), and the caller computes that boolean by comparing the open Semester's name to
 * the Student's `enrollment_term`. The **approaching-graduation** exception is deferred to
 * Phase 5 (it needs Remaining CH, which this phase does not compute); when it lands it
 * adds a field here and folds into {@see self::waivesFloor()} without reshaping the
 * validator's signature. There is no ceiling exception and no advisor override.
 *
 * An immutable value object.
 */
final readonly class CreditHoursExceptions
{
    /**
     * @param  bool  $firstSemester  whether the target Semester is the Student's first
     *                               (their enrollment term) — waives the CH floor
     */
    public function __construct(
        public bool $firstSemester = false,
    ) {}

    /** No exceptions apply — the full floor and ceiling are enforced. */
    public static function none(): self
    {
        return new self;
    }

    /**
     * Whether the credit-hour floor is waived for this registration. Only the
     * first-semester exception waives it in Phase 3; Phase 5's approaching-graduation
     * exception will be OR-ed in here.
     */
    public function waivesFloor(): bool
    {
        return $this->firstSemester;
    }
}
