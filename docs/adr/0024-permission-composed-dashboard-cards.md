# A Dashboard is composed from the acting User's access, not selected by Role — and it is made of Cards the owning modules supply

**Status:** accepted — departs deliberately from the wording of SRS §2.3 and architecture.md

[SRS §2.3](../SRS.md) lists "**role-based dashboards** for all user types" as a scope bullet, and
[architecture.md](../architecture.md) repeats it as "each role sees a tailored dashboard". Taken
literally, both describe a lookup: *find the acting user's role, render that role's page.* The
system this project actually built cannot answer that, because it does not model a user as having
*a* role. A **User** holds **many Roles** whose **Permissions union**
([CONTEXT.md](../../CONTEXT.md)), the seeded `multi@` account holds Advisor *and* Instructor, and
the side nav has composed itself from the permission union since Phase 0. A role-selected
dashboard would need to pick one of a user's roles — or offer a "view as role" switch — and either
choice puts a user in a mode where they are looking at an incomplete picture of their own work.

**A Dashboard is therefore composed from the acting User's access.** A User holding several Roles
sees the **union** of their Cards on one page, and there is **no "view as role" control**. This
ADR exists largely to record *why the code disagrees with those two documents*, so a future reader
finding permission-composed Cards and no role switcher does not read it as an oversight. The
phase's own name drops the words "role-based" for the same reason.

## What a Card is

A **Dashboard Card** is one summary on the Dashboard, **owned by the module whose data it
summarises**, gated on a single Permission or Role, showing a count or short list plus a link into
the full surface behind it. A Card introduces **no domain logic of its own** — every figure is
read from the derivation that already owns it, never recomputed and never cached, so a Card can
never disagree with the screen it links into.

No `Dashboard` module is created. A Dashboard is a *view*, not a bounded context, and it owns no
domain language ([ADR-0002](0002-modular-monolith-seams.md)). The same rule holds on both sides of
the wire, and on both sides it is forced by the seam checker rather than merely agreed:

- **API** — one endpoint per Card, registered from its owning module's own `Public` route file and
  **independently authorized**. No new deptrac edge is needed, because a Card reads only its own
  module's data. (One endpoint *per module* was the design session's shorthand; a module owning
  Cards behind different Permissions — Admissions owns three — cannot be `can:`-gated as one
  route, so the faithful implementation is one endpoint per Card. The load-bearing property is
  unchanged.)
- **SPA** — a Card component lives in the feature that owns its data, and only the app shell
  composes. `eslint-plugin-boundaries` forbids a feature importing another feature, which is also
  why the landing page **moved out of the auth feature into the app shell**; the route stays `/`.

*Owner supplies the Card, shell arranges it* — the rule the permission-filtered side nav already
follows.

## The three gates

The permission-to-Card mapping is a **declarative table** in the app shell beside the navigation
table. It is *data, not logic*: there is deliberately no selection function, because the table is
the specification and the page reads it directly.

1. **Permission — the default.** A Card is gated on exactly one Permission from the §3.2 matrix.

2. **Role — for persona Cards.** A Card whose data hangs off a persona record the acting user may
   not possess (a Student anchor, an Applicant's Application) gates on the **Role**. This is **not
   a new mechanism**: an administrator inherits `courses.register_self` and `grades.view_own` under
   [ADR-0008](0008-rbac-enums-static-matrix.md) while holding no Student anchor, so a persona
   surface gated on the Permission renders broken for them — the convention the persona nav items
   have followed since Phase 2 and Phase 3 ([ADR-0015](0015-administrative-override-of-scope-guards.md)).
   Phase 4.5 applies the existing rule to Cards.

3. **The administration set — instead of the union.** A User holding R09/R10 sees the
   Administration Card plus the system-configuration Cards, and **no queue or persona Cards**.
   Holding every Permission is an authorization fact, not a description of an administrator's job.
   The set ships empty (until the Administration Card does), so an administrator lands on a
   correct, deliberately empty Dashboard rather than accumulating every other role's queue and
   having it taken away later.

**A Card exists because a feature shipped it.** Permission is a Card's *gate*, never its
*trigger*: the Permissions in the enum whose features arrive in Phases 5 and 6 contribute no Card
and no placeholder, and each of those phases adds its own Card as part of its own work.

## Consequences accepted

- **The client composes; the server authorizes.** The SPA chooses which Cards to request from the
  roles and permissions `/me` already returns. That is a **UX mirror only**
  ([ADR-0014](0014-client-validation-mirrors-server-rules.md)) — every Card endpoint re-authorizes
  its own request, and a test asserts the **absence** of Cards a user must not see, so a
  permission regression fails the build rather than leaking a surface.
- **Cards fetch and fail independently.** One Card erroring renders that Card's error state; the
  grid around it is intact. The cost is one request per Card on the most-visited screen in the
  application — accepted, because the alternative (one aggregate endpoint) cannot be `can:`-gated
  per Card and would make the whole page fail together.
- **Typed domain values only.** A Card payload carries ids, codes, enum values, counts and dates —
  **never pre-rendered display strings** — because the SPA renders bilingually from the data,
  exactly as Notifications does.
- **Counts respect ownership seams.** Owner-local scalar sets use database aggregate queries.
  When rows must already be materialised for business-day arithmetic, cross-module Public-facade
  scoping, or a bounded preview, the Card may reuse and count that set instead of issuing a second
  query solely for the number.
- **A Card is not a Report.** A Card is fixed, scoped to the acting user, and summary; a Report
  (SRS §4.12, Phase 7) is filterable, parameterised and cross-student. Catalog Summary and Phase
  7's Enrollment Summary report are different objects. Recorded here so Phase 7 does not
  re-litigate it.
- **The term is "Dashboard Card", never "Panel".** `InterviewPanel`, `CurriculumPanel`,
  `VersionHistoryPanel` and `CartPanel` already ship using "Panel" as a generic word for a
  bordered page section; adopting it as a domain term would put four shipped components in
  violation of the ubiquitous language.
