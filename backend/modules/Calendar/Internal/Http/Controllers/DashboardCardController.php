<?php

namespace Modules\Calendar\Internal\Http\Controllers;

use Modules\Calendar\Internal\Http\Resources\DashboardCardResource;
use Modules\Calendar\Internal\Support\SemesterWindowsCardBuilder;

/**
 * @group Calendar
 *
 * Calendar's Dashboard Card (phase 4.5, ADR-0024) — one of the two Cards about how the system is
 * **configured** rather than about a queue of work. Read-only, living in the module that owns the
 * data, independently authorized by its own route gate, and returning typed domain values
 * (enum values, ids, ISO dates, window keys) rather than pre-rendered display strings: the SPA
 * renders every window name bilingually from the payload, exactly as Notifications does.
 *
 * A Card introduces no domain logic of its own. Window openness is read from ADR-0018's derived
 * period state and never from a stored flag — the "derived, never stored" house rule — so this
 * Card can never disagree with the Semester management screen it links into.
 */
class DashboardCardController
{
    /**
     * Semester and Open Windows Card.
     *
     * The live Semester, which of its period windows are open right now, and the next date due.
     * The Semester is the one **running** (start ≤ now ≤ end), or — when no term is running —
     * the one whose registration window is open, which is the term an administrator is
     * configuring. `open_windows` is a list because the windows overlap: registration may still
     * be open past the semester start, while `current_period` names the single phase the
     * Semester reads as. Every field is `null`/empty when no Semester is live at all, so the
     * Card renders its empty state rather than an error.
     *
     * Gated on `calendar.manage` (§3.2 — Student Affairs / Dean; administrators inherit it).
     *
     * The empty case is described above rather than given its own `@response` scenario: a second
     * 200 whose body is a *different shape* — nulls where the first has objects — makes Scribe emit
     * a union response type, and the generated Orval client then cannot read either branch. Same
     * shape, several scenarios is fine; different shapes are not.
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "current_period": "add_drop", "open_windows": ["registration", "add_drop"], "next_deadline": {"window": "add_drop", "at": "2026-09-14T00:00:00+00:00"}}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function semesterWindows(SemesterWindowsCardBuilder $card): DashboardCardResource
    {
        return new DashboardCardResource($card->build());
    }
}
