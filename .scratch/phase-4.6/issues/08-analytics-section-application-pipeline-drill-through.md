# 08 — Analytics section + Application Pipeline Report + drill-through

**What to build:** The phase's second tracer bullet, and the first time this system answers a
question rather than listing records. A Dean opens **Analytics**, picks an Admission Cycle, and
sees the Application Pipeline: how many Applications sit at each status, the conversion rate
between stages, and where Applicants are being lost rather than only how many remain. They click
the bar for one status and land on **exactly those Applications**, filtered — and export them.

This ticket establishes everything the four later Reports fill in.

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
  to the queue behind it.

**Blocked by:** 02 — Query contract foundation; 04 — Batched owner-facade derivation; 05 — Chart primitive;
06 — Admissions and Catalog lists on the contract.

**Status:** ready-for-agent

- [ ] An Analytics nav section exists, permission-filtered like the existing sections, and is
      absent for users holding no Report gate
- [ ] The Application Pipeline Report renders counts per Application Status and conversion rates
      between stages, from the ticket-04 aggregate
- [ ] Its parameters (Admission Cycle, Program, degree level) use the shared `<FilterBar>` and live
      in the URL
- [ ] The chart uses the `shared/ui` primitive and carries its "View as table" disclosure
- [ ] Clicking a segment navigates to the Applications list pre-filtered to exactly that segment,
      and the row count matches the segment's figure
- [ ] The list drilled into is exportable with the segment's filters applied
- [ ] The endpoint is gated on `application.screen` and returns 403 without it
- [ ] A Dean's Report is faculty-scoped by the same derivation as their queue
- [ ] Payload carries typed domain values only — no pre-rendered display strings
- [ ] Pest covers the authorized payload, the 403, the scoping and the empty case
- [ ] `openapi.yaml` and the Orval client regenerated and committed
- [ ] `composer gate` and `npm run gate` green
