# 08 — Analytics section + Application Pipeline Report + drill-through

**What to build:** The phase's second tracer bullet, and the first time this system answers a
question rather than listing records. A Dean opens **Analytics**, picks an Admission Cycle, and
sees the Application Pipeline: how many Applications sit at each status, the conversion rate
between stages, and where Applicants are being lost rather than only how many remain. They click
the bar for one status and land on **exactly those Applications**, filtered — and export them.

This ticket establishes everything the five later Reports fill in.

- **A Report is a first-class term now** (CONTEXT.md), and this is the first one: parameterised,
  cross-record, and drillable. The mechanical line against a Dashboard Card is that **a Card takes
  no parameter**; the moment it would, it is a Report.
- **No Reporting module.** The Report lives in Admissions, which owns its spine data, and reaches
  Catalog and Identity through edges deptrac **already grants** — so this phase adds zero new
  edges. The SPA composes one Analytics section: *owner supplies, shell arranges*, the rule the side
  nav and the Dashboard already follow. The Analytics nav section is permission-filtered exactly as
  the existing sections are.
- **Parameters ride the existing query contract.** The Report's parameter bar is the same
  `<FilterBar>` the grids use, its state lives in the URL, and the Report is therefore bookmarkable
  and shareable like any filtered view. One control to learn, not two.
- **Drill-through is an ordinary link.** A chart segment navigates to the Applications list with
  the segment's filters in the query string — which is why URL-as-source-of-truth was chosen in the
  first place. The list it lands on is the one ticket 06 already made filterable and exportable, so
  no new surface is needed and the numbers cannot disagree: the bar and the rows are the same query.
- **The gate is `application.screen`**, the Permission that already authorizes reading the queue —
  an exact match to what SRS §4.12 asks for, and the only one of the six Reports where the SRS role
  list and the §3.2 matrix agree precisely. Dean scope applies to the Report exactly as it applies
  to the queue behind it; Student Affairs uses the queue's existing unscoped view.

**Blocked by:** 02 — Query contract foundation; 04 — Batched owner-facade derivation; 05 — Chart primitive;
06 — Admissions and Catalog lists on the contract.

**Status:** ready-for-human

- [x] An Analytics nav section exists, permission-filtered like the existing sections, and is
      absent for users holding no Report gate
- [x] The Application Pipeline Report renders counts per Application Status and conversion rates
      between stages, from the ticket-04 aggregate
- [x] Its parameters (Admission Cycle, Program, degree level) use the shared `<FilterBar>` and live
      in the URL
- [x] The chart uses the `shared/ui` primitive and carries its "View as table" disclosure
- [x] Clicking a segment navigates to the Applications list pre-filtered to exactly that segment,
      and the row count matches the segment's figure
- [x] The matching "View as table" row offers the same drill-through as a keyboard-operable link
      and lands on the identical filtered URL
- [x] The list drilled into is exportable with the segment's filters applied
- [x] The endpoint is gated on `application.screen` and returns 403 without it
- [x] A Dean's Report is faculty-scoped by the same derivation as their queue
- [x] Student Affairs sees the complete unscoped cohort; Dean tests include in-scope and
      out-of-scope rows, and both roles prove filters only narrow their base scope
- [x] Payload carries typed domain values only — no pre-rendered display strings
- [x] Pest covers the authorized payload, the 403, the scoping and the empty case
- [x] `openapi.yaml` and the Orval client regenerated and committed
- [x] `composer gate` and `npm run gate` green

## Phase 7 prerequisite disposition

This ticket is in the authorized executable closure for Phase 7; its implementation
is on `phase-7-academic-reports-bilingual`. Existing capabilities were verified
before extending them. See [the Phase 7 tracker](../../phase-7/tracker.md) for
focused tests, runtime evidence and the fixed review baseline, and
[the consolidated review](../../phase-7/review.md) for resolved Standards/Spec
findings. Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend 684 unit tests and 179 serial browser checks. The additional CI timing
regression and two affected workflows passed (3/3, 12.4m); see tracker for the
180-test discovery versus 179-test full-gate distinction. Any by-hand acceptance remains pending. Other Phase 4.6 tickets are
outside this goal and are not marked complete by this disposition.
