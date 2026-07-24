<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;

/**
 * One resolved line of a registration cart, as the pure {@see CartValidator} sees it —
 * a Course Offering the Student put in their cart, with every fact the batch rules need
 * already resolved by the controller: the Offering and its Course id, the course code
 * (for human 422 messages), the requested {@see RegistrationMode}, the course's total CH,
 * the {@see EnrollmentClassification} the {@see EligibilityClassifier} derived, the seats
 * still available (null = uncapped), and the Offering's weekly {@see MeetingTime} slots.
 *
 * A line only exists for an *eligible* Offering — the controller rejects a cart line whose
 * Offering it cannot classify before building this — so {@see $classification} is always
 * present. An immutable value object, so the whole cart rule set is unit-testable without
 * the HTTP stack or the database.
 */
final readonly class CartLine
{
    /**
     * @param  list<MeetingTime>  $meetings
     */
    public function __construct(
        public int $offeringId,
        public int $courseId,
        public string $courseCode,
        public RegistrationMode $mode,
        public int $totalCh,
        public EnrollmentClassification $classification,
        public ?int $seatsRemaining,
        public array $meetings,
    ) {}
}
