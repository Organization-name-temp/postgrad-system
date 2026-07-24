<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * One reason a registration cart cannot be submitted (spec §Registration workflow):
 * a stable machine {@see $code}, a human {@see $message} for the 422, and the ids of the
 * Offerings the violation is about, so the controller can enumerate the offending lines
 * (an illegal submission returns "the offending lines/reasons"). An immutable value object
 * produced by the pure {@see CartValidator}.
 */
final readonly class CartViolation
{
    /** Cart totals below the (unwaived) credit-hour floor. */
    public const string BELOW_MIN_CREDIT_HOURS = 'below_min_credit_hours';

    /** Cart totals above the credit-hour ceiling. */
    public const string ABOVE_MAX_CREDIT_HOURS = 'above_max_credit_hours';

    /** The same course appears on more than one line (incl. Regular + Audit of it). */
    public const string DUPLICATE_COURSE = 'duplicate_course';

    /** An approved line is a course the Student is already enrolled in this semester (mint). */
    public const string DUPLICATE_ENROLLMENT = 'duplicate_enrollment';

    /** A second retake of a course whose one allowed retake is already used (mint, FR-REG-006). */
    public const string RETAKE_LIMIT = 'retake_limit';

    /** Two chosen Offerings meet at overlapping weekly times (or clash with the record). */
    public const string TIME_CLASH = 'time_clash';

    /** A chosen Offering has no seats remaining. */
    public const string OFFERING_FULL = 'offering_full';

    /** More than one External Elective in the cart (the one-allowance cap, BR-24). */
    public const string EXTERNAL_ELECTIVE_CAP = 'external_elective_cap';

    /** More than one Research Course in the cart (the exactly-one cap, BR-25). */
    public const string RESEARCH_COURSE_CAP = 'research_course_cap';

    /**
     * @param  list<int>  $offeringIds  the Offerings this violation concerns
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $offeringIds = [],
    ) {}
}
