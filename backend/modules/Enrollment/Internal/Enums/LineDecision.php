<?php

namespace Modules\Enrollment\Internal\Enums;

use Modules\Enrollment\Internal\Models\RegistrationRequestLine;

/**
 * The Advisor's outcome for one {@see RegistrationRequestLine} (ticket 06): approved (it
 * mints an Enrollment) or rejected (it is trimmed with the request's note). Null on a line
 * until the request is decided. A closed set in code, Internal to Enrollment (ADR-0008);
 * the value crosses to the SPA as its plain string on the request view.
 */
enum LineDecision: string
{
    /** The Advisor kept this line — it mints an Enrollment on approval. */
    case Approved = 'approved';

    /** The Advisor trimmed this line — no Enrollment is minted for it. */
    case Rejected = 'rejected';
}
