<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * The credit-hour rule exceptions that apply to one Student's registration in one
 * Semester — the context the pure {@see CreditHoursValidator} reads to decide whether the
 * 6-CH floor is waived (spec §Rules engines, FR-REG-001).
 *
 * Both exceptions are completion rules computed from Enrollment-owned academic facts and
 * Catalog's immutable Curriculum Version. There is no ceiling exception or advisor override.
 *
 * An immutable value object.
 */
final readonly class CreditHoursExceptions
{
    /**
     * @param  bool  $firstSemester  proposed schedule completes every remaining
     *                               first-Semester Preparatory target
     * @param  bool  $approachingGraduationCompletion  proposed schedule completes every
     *                                                 remaining Curriculum requirement
     */
    public function __construct(
        public bool $firstSemester = false,
        public bool $approachingGraduationCompletion = false,
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
        return $this->firstSemester || $this->approachingGraduationCompletion;
    }
}
