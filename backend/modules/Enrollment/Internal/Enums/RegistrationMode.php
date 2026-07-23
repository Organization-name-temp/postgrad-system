<?php

namespace Modules\Enrollment\Internal\Enums;

/**
 * How a Student wants to take one course on a {@see
 * \Modules\Enrollment\Internal\Models\RegistrationRequest} line — for **credit**
 * (Regular) or to **sit it without credit** (Audit) (FR-REG-001, Appendix D). Both count
 * toward the cart's credit-hour load; an Audit adds the attendance obligation Phase-3
 * attendance flags (ticket 08).
 *
 * A Student never requests **Retake** — it is *derived* at mint from the Student's record
 * (a prior for-credit attempt of the same course, ticket 06), never chosen — so it is not
 * a mode a cart line can carry. A closed set in code (ADR-0008), Internal to Enrollment:
 * the value crosses to the SPA as its plain string (the per-line Regular/Audit toggle),
 * so no cross-module consumer references this type by name.
 */
enum RegistrationMode: string
{
    /** Taken for credit — the default. */
    case Regular = 'Regular';

    /** Sat without credit; still carries the course's credit-hour load and attendance. */
    case Audit = 'Audit';
}
