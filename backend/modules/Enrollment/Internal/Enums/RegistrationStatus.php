<?php

namespace Modules\Enrollment\Internal\Enums;

use Modules\Enrollment\Internal\Models\RegistrationRequest;

/**
 * Where a {@see RegistrationRequest} sits in the
 * advisor-review pipeline (FR-REG-003, ADR-0017). A submitted cart is born **Pending**;
 * the assigned Advisor's line-item decision (ticket 06) moves it to **Approved** (≥1
 * line approved, Enrollments minted) or **Rejected** (all lines rejected). There is no
 * server-side Draft — the cart lives in the SPA until submit (spec §Registration
 * workflow). At most one Pending request exists per (student, semester); a Rejected
 * request may be replaced by a fresh cart while the window is open.
 *
 * A closed set in code (ADR-0008), Internal to Enrollment: the value crosses to the SPA
 * as its plain string (the request view renders the status), so no cross-module consumer
 * references this type by name.
 */
enum RegistrationStatus: string
{
    /** Submitted, awaiting the assigned Advisor's decision. */
    case Pending = 'Pending';

    /** The Advisor approved ≥1 line; Enrollments were minted (ticket 06). */
    case Approved = 'Approved';

    /** The Advisor rejected every line; the Student may submit a new cart. */
    case Rejected = 'Rejected';
}
