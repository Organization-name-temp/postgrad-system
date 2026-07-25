<?php

namespace Modules\Grading\Internal\Enums;

/**
 * The state of one Grade Sheet round (ADR-0023) — the complete closed set, in the code-owned
 * static-matrix style of Enrollment's CourseStatus and Admissions' ApplicationStatus
 * (ADR-0008). The state machine lives on the **Sheet**, never on an individual Grade: the
 * Instructor signs a whole course as one act, which is what SRS §4.7.1.4's "forwarded to the
 * Department Head" describes.
 *
 * ```
 * Draft ──sign──▶ Submitted ──finalize──▶ Finalized   (terminal — never reopened)
 *   ▲                  │
 *   └──── Returned ◀───┘ (with a note)
 * ```
 *
 * All four cases ship up front so later tickets wire behaviour onto a stable type, but only
 * **Draft** is reachable in ticket 01 — nothing is signed or reviewed yet. Ticket 03 builds
 * the transitions and makes an illegal one a 409, as the Admissions status machine already
 * does. Grading-owned and Internal: the value crosses to the SPA as its plain string (the
 * Sheet's StatusChip), so no cross-module consumer names this type.
 */
enum GradeSheetStatus: string
{
    /** Being filled in — marks are editable by the Instructor (and by SA, ticket 09). */
    case Draft = 'Draft';

    /** Signed by the Instructor and awaiting Department-Head review; read-only to everyone. */
    case Submitted = 'Submitted';

    /** Sent back by the Department Head with a note; editable and re-signable again. */
    case Returned = 'Returned';

    /** Finalized by the Department Head — part of the academic record, and permanently closed. */
    case Finalized = 'Finalized';

    /**
     * Whether marks may be written on a Sheet in this state. A Submitted Sheet is read-only to
     * everyone including its own Instructor — a mark under review cannot be quietly altered —
     * and a Finalized Sheet is closed forever; correcting one means opening a new Correction
     * round, never reopening this one.
     */
    public function acceptsMarks(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
