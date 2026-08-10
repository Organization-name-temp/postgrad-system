<?php

namespace Modules\Grading\Internal\Enums;

/**
 * What one Grade Sheet round is *for* (ADR-0023) — the closed set, in the ADR-0008
 * static-set-in-code style. A Sheet is a round, not a row per Offering: an Offering
 * accumulates one Initial round and then a follow-up round per batch of later changes. The
 * kind is the only thing that distinguishes them, which is why the same state machine and the
 * same two-party review serve all three and no grade escapes a second pair of eyes.
 *
 * All three cases ship up front; only **Initial** is reachable in ticket 01. Grading-owned and
 * Internal: the value crosses to the SPA as its plain string.
 */
enum GradeSheetKind: string
{
    /** The Offering's first round — every gradable Enrollment on the roster is a line. */
    case Initial = 'Initial';

    /** A later round resolving one or more Incompletes (ticket 05) — only those lines. */
    case IncompleteResolution = 'IncompleteResolution';

    /** A later round correcting a finalized mark (ticket 06) — only the affected lines. */
    case Correction = 'Correction';
}
