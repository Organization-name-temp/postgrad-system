<?php

namespace Modules\Grading\Internal\Events;

use Modules\Grading\Internal\Listeners\SendGradeFinalizedNotifications;

/**
 * A Grade Sheet round was finalized (ADR-0023, ticket 03) — dispatched by the Department-Head
 * review controller **after** the finalize transaction (the transition, the `earned` writes
 * and the state change) has committed, so a listener only ever reacts to a real, persisted
 * finalize. Mirrors the project's first domain event, Admissions' `AdmissionAccepted`
 * (ADR-0013).
 *
 * The event carries side-effects only — never the write itself. Its ticket-03 listener
 * ({@see SendGradeFinalizedNotifications}) notifies each
 * graded Student; Phase 5's standing recompute hangs its own listener off this same event
 * without touching the finalize.
 */
final class GradeFinalized
{
    public function __construct(
        public int $gradeSheetId,
    ) {}
}
