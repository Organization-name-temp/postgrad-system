<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Calendar\Public\SemesterSummary;
use Modules\Enrollment\Internal\Enums\RegistrationKind;

/**
 * The Semester a Student may submit a cart for right now, and the kind of request it would
 * be — the answer to "is registration submittable, and as what?" (ticket 05/07). It pairs
 * the open {@see SemesterSummary} with its {@see RegistrationKind}: `Initial` while the
 * registration window is open, or `LateAdd` when the Semester is in its late-add period
 * (FR-REG-004). A small immutable value object so the eligible list, the submit, and the
 * request view all read the pair the same way rather than passing a bare array around.
 */
final readonly class RegistrationTarget
{
    public function __construct(
        public SemesterSummary $semester,
        public RegistrationKind $kind,
    ) {}
}
