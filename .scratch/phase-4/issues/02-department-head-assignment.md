# 02 — Department-Head assignment

**What to build:** A System Administrator assigns a Department Head to a Department so that
signed Grade Sheets have somewhere to go, and can also assign a Head to the
**university-wide** scope — the Preparatory courses (PRE401–404) that belong to no
Department at all. The screen shows which Departments currently have no Head, so an
administrator can spot grades that would otherwise strand in review.

This mirrors Phase 2's Dean↔Faculty assignment in shape and in gating: the surface is gated
on holding the **R09/R10 role**, not on a new permission — the SRS §3.2 matrix stays exactly
as ADR-0008 froze it.

The one non-obvious part: the assignment's Department reference is **nullable**, NULL meaning
the university-wide Preparatory scope, mirroring how
[ADR-0010](../../../docs/adr/0010-catalog-curriculum-model.md) already models
`Course.department_id`. That symmetry is what lets ticket 03 route every Sheet with a single
rule and no special case. See
[ADR-0023](../../../docs/adr/0023-grade-sheet-submission-rounds.md).

This ticket touches no grading code and is independent of ticket 01 — the two can run in
parallel.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Identity owns a Department-Head assignment: a User holding R06, and a **nullable** opaque Department reference (no foreign key across the seam, ADR-0012), unique per (user, Department) — including at most one university-wide assignment per user.
- [ ] `Identity\Public` exposes the read contract other modules use to resolve the Head(s) for a Department, and for the university-wide scope. A facade test covers both, plus a Department with no Head.
- [ ] Assigning a user who does not hold R06 is rejected with a bilingual 422.
- [ ] The management endpoints are reachable only by a user holding R09 or R10; every other role gets a 403, including R06 themselves.
- [ ] Each assignment write records an Identity audit entry.
- [ ] The admin screen joins the existing assignments area beside the Dean assignments: list current assignments, create and remove them, and surface Departments with no Head as a distinct, visible state.
- [ ] The university-wide scope is presented as an explicit named choice, not a blank or absent Department — a reader must not have to infer what an empty cell means.
- [ ] Bilingual EN + AR throughout, including the university-wide label.
- [ ] Pest feature tests at the API seam cover assignment, removal, the R06 requirement, the R09/R10 gate, uniqueness, and the audit row. Vitest covers the screen's non-trivial behaviour.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; the screen passes axe with no serious or critical violations.
