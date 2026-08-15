# A Report is owned by the module that owns its spine data; there is no Reporting module

**Status:** accepted — departs deliberately from SRS §4.12 and from ROADMAP Phase 7's own wording

[SRS §4.12](../SRS.md) is titled "Reporting & Analytics **Module**" and
[ROADMAP.md](../ROADMAP.md) Phase 7 repeats it — "**Reporting module:** the 8 read-only
cross-module reports". Both read naturally, because a Report genuinely *is* cross-module: an
Enrollment Summary spans Identity, Catalog and Enrollment; a GPA Distribution spans Grading,
Catalog and Identity. A thing that reads from everywhere looks like it wants to live somewhere new.

It does not, and [ADR-0024](0024-permission-composed-dashboard-cards.md) already settled why in a
neighbouring case: it refused a `Dashboard` module because a Dashboard "is a *view*, not a bounded
context, and it owns no domain language". A Report is the same kind of object. Accepting a
Reporting module having refused a Dashboard module would be inconsistent, and it would cost seven
new deptrac edges to boot.

**Decision: each Report lives in the module that owns its spine data, reaching sideways through
`Public` facades, and the SPA composes one Analytics section from them.** *Owner supplies, shell
arranges* — the rule the permission-filtered side nav has followed since Phase 0.5 and the Dashboard
since Phase 4.5, applied a third time.

The decisive finding is that **this costs no new deptrac edges at all**. Every Report has a natural
owner whose outbound edges are already granted:

| Report | Owner | Reaches | Edge status |
|---|---|---|---|
| Application Pipeline | Admissions | Catalog, Identity | already granted |
| Enrollment Summary | Enrollment | Catalog, Identity, Calendar | already granted |
| Course Registration Summary | Enrollment | Catalog, Calendar | already granted |
| Attendance Analytics | Enrollment | Catalog, Identity, Calendar | already granted |
| GPA Distribution | Grading | Catalog, Identity, Enrollment, Calendar | already granted |
| Audit Log | Identity | own data only | none needed |

**Identity is the one module with no outbound edges**, which decides an otherwise arguable case:
"students per Program" reads most naturally off the Student anchor that Identity owns
([ADR-0013](0013-acceptance-handoff-student-anchor.md)), but Identity cannot reach Catalog to
resolve a Program and must not gain that edge for a view. **Enrollment Summary is therefore owned
by Enrollment**, which already reads both Catalog and Identity.

## What gates a Report

**Each Report is gated on the existing Permission that already authorizes reading its underlying
data**, so a Report can never reveal anything its source screens do not. No new Permission is
added: the enum's own docblock calls the set "university bylaws, not runtime-editable"
([ADR-0008](0008-rbac-enums-static-matrix.md)), and SRS §4.12 supplies no permission names to
transcribe — only a table of roles.

Two rules decide which Permission:

1. **A Report is gated by the Permission authorizing its underlying data**, so it discloses nothing
   its source screens do not.
2. **A Report's gate is at least as strong as the gate on the list it drills into.** Aggregation is
   not a weakening argument here, because every chart segment is a link to the identifying rows
   behind it. A Course Registration Summary is only counts per Offering — but it drills into a
   roster, so it is gated at the roster's level, not the count's.

That table and the §3.2 matrix do not align, so the divergences are recorded here rather than
absorbed silently:

| Report | Gate | §4.12 says | Divergence |
|---|---|---|---|
| Application Pipeline | `application.screen` | SA, Dean | none — exact |
| GPA Distribution | `grades.view_student` | SA, Dean, DH | also reaches Advisor, Instructor, Council |
| Enrollment Summary | `grades.view_student` | SA, Dean, DH, SysAdmin | also reaches Advisor, Instructor, Council |
| Course Registration Summary | `grades.view_student` | SA, Advisor, DH | also reaches Instructor, Dean, Council |
| Attendance Analytics | `grades.view_student` | not in §4.12 | an [ADR-0019](0019-attendance-session-register.md) extension |
| Audit Log | `ADMIN_ROLES` (R09/R10) | SysAdmin, Super Admin | none — role-gated, the AdministrationCard precedent |

**`grades.view_student` gates four of the six, and it is doing the work of a permission §3.2 does
not have: "may read student academic records".** Its holders — Advisor, Instructor, Student
Affairs, Department Head, Dean, Council — are exactly the staff who may already open an individual
student's record, and notably exclude Student and Applicant, which `catalog.view` and
`standing.monitor` would not. Every divergence above is therefore in the same direction: a Report
reaches *more staff* than §4.12 lists, and **no one who could not already read the same facts one
student at a time**. The alternative — inventing a `reports.view` case — was rejected under
[ADR-0008](0008-rbac-enums-static-matrix.md); if a future report makes this stretch untenable, this
is the paragraph to revisit.

Attendance Analytics is additionally scoped by assignment, as the register itself is: an Instructor
sees their own Offerings, a Department Head their Department, Student Affairs and Dean all.

Row-level scope applies to a Report exactly as it applies to the list behind it: a Dean's Pipeline
is their Faculty's, a Department Head's grade distribution is their Department's
([ADR-0025](0025-support-layer-query-contract.md)).

## Consequences

- **Phase 7 shrinks to what genuinely depends on Phase 5** — Academic Standing, Graduation
  Readiness and Termination — plus the i18n polish pass. Five of SRS §4.12's eight reports close in
  Phase 4.6, and a sixth (Attendance) is added that §4.12 never listed.
- **A Report is parameterised and drillable.** Its parameters ride the same `Support` query contract
  the grids use, and a chart segment navigates to the filtered list behind it — which is itself
  exportable. One contract drives grid, parameters, drill-through and export, so a bar and the rows
  it stands for cannot disagree.
- **The distinction ADR-0024 drew now has a term on both sides.** A **Card** is fixed, scoped to
  the acting user and summary; a **Report** is parameterised and cross-record. Both entries are in
  [CONTEXT.md](../../CONTEXT.md). The operational test is mechanical: *if it needs a parameter, it
  is a Report.*
- **A Report with no natural owner is the signal to revisit this**, not to force a home. If one
  arrives that genuinely spans modules with no spine, that is the evidence a Reporting module was
  right after all — and this ADR should be superseded rather than worked around.
- The `App` layer's missing `Admissions` and `Notifications` edges (recorded in
  [HANDOFF.md](../../HANDOFF.md) since Phase 4.5) stay **recorded, not fixed**: nothing in `app/`
  needs them, because global search is a client-side fan-out over each module's own list endpoint
  rather than a server-side composition.
