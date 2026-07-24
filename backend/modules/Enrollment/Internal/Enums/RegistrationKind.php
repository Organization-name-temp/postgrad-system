<?php

namespace Modules\Enrollment\Internal\Enums;

use Modules\Enrollment\Internal\Models\RegistrationRequest;

/**
 * What a {@see RegistrationRequest} adds to a
 * Student's schedule (FR-REG-004, ADR-0017). An **Initial** request is the first
 * schedule a Student submits for a Semester while the registration window is open — the
 * only kind ticket 05 creates. A **LateAdd** request is the add-only week-3 request a
 * Student with an approved Initial schedule submits during the late-add window
 * (ticket 07); it is modelled here so the aggregate ships whole, but is not yet reachable.
 *
 * A closed set in code, in the same style as the Catalog/Identity enums (ADR-0008), and
 * Internal to Enrollment: the value crosses to the SPA as its plain string, so no
 * cross-module consumer references this type by name.
 */
enum RegistrationKind: string
{
    /** The Student's first schedule for a Semester, submitted while registration is open. */
    case Initial = 'Initial';

    /** An add-only late-add request during week 3 of the term (ticket 07). */
    case LateAdd = 'LateAdd';
}
