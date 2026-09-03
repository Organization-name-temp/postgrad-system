# Spec — Phase 4.5: Dashboards

**Status:** ready-for-agent
**Feature slug:** phase-4.5
**Phase:** 4.5 of the [Build Roadmap](../../docs/ROADMAP.md) — a composition slice inserted between Phase 4 (Grading) and Phase 5 (Standing), exactly as Phase 0.5 was inserted between Phase 0 and Phase 1. Phases 5, 6 and 7 keep their existing numbers and order.
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [SRS §2.3](../../docs/SRS.md) ("Role-based dashboards for all user types" — a scope bullet the ROADMAP never assigned to a phase) · [SRS FR-ADM-005.5](../../docs/SRS.md) (pending application count and aging on the Student Affairs dashboard — unbuilt since Phase 2) · [architecture.md §Routing](../../docs/architecture.md) · [design-system.md](../../docs/design-system.md) · [CONTEXT.md](../../CONTEXT.md) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)/[0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)/[0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md)/[0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)/[0015](../../docs/adr/0015-administrative-override-of-scope-guards.md) · **new ADR-0024** (permission-composed dashboard cards)

---

## Problem Statement

Four phases have shipped real domain machinery — Admissions, Catalog, Enrollment, Grading —
and **every one of them is reachable only by navigating into it.** The authenticated landing
page is still the Phase-0 walking-skeleton stub: it renders the signed-in user's name, email,
role chips, and a wall of every effective permission as chips. That page is a developer's
authorization fixture, and it is the first thing every user of every role sees on every login.

- **A Student Affairs officer** has no idea how much work is waiting. SRS FR-ADM-005.5 requires
  a pending-application count with aging on their dashboard; it has never been built, so the
  only way to learn that six applications are overdue is to open the screening queue and count.
- **An Instructor** cannot tell from the landing page that a grade sheet is unsigned, that a
  submission deadline has passed, or that an Incomplete is about to convert to a Fail
  Withdrawal — all facts the Grading module already knows.
- **A Student** cannot see their CGPA without navigating to My Grades. The single number the
  whole degree turns on is two clicks and one menu away from the page they land on.
- **An Advisor** cannot see that registration requests are waiting for approval; the advisee
  queue is only discoverable by opening it.
- **A Department Head** lands on the same permission-chip wall as everyone else, with a review
  queue they must go looking for.
- **A user holding several roles** — the seeded `multi@` account holds Advisor *and* Instructor —
  gets no acknowledgement of that at all. Nothing in the app is composed from a user's full
  permission set except the side nav.
- **"Dashboard" is undefined in the ubiquitous language** while meaning three different things
  across the project's own documents: the SRS §2.3 role-based landing pages, the SRS §4.12
  reporting module, and the SRS FR-ADM-005.5 Student Affairs operational view. The word appears
  in [CONTEXT.md](../../CONTEXT.md) not at all.
- **The seeded demo world cannot demonstrate any of this.** The canonical `r01@…r10@` accounts
  are authorization fixtures: `r02@` (Student), `r03@` (Advisor) and `multi@` carry no data at
  all, because every persona with real records lives under the named `advisor.*` / `student.*`
  namespace. That was invisible while every screen was a list you navigate *into*; it becomes
  glaring the moment the landing page is made of counts.

The result is a system whose parts all work and which **presents itself as an unfinished
prototype** at the exact moment a first-time viewer forms their impression of it.

## Solution

Build the **Dashboard**: the authenticated landing surface, composed of **Dashboard Cards** —
small, scoped summaries drawn from the modules that already own their data, selected by the
acting user's own access, and each linking into the full surface behind it.

The composition rule is the heart of the phase and is recorded in **ADR-0024**. A Dashboard is
**not** selected by Role and there is **no "view as role" switch**: a User holding several Roles
sees the union of their Cards on one page. This deliberately departs from the wording of
[SRS §2.3](../../docs/SRS.md) and [architecture.md](../../docs/architecture.md) ("each role sees a
tailored dashboard"), because the system already models a User as holding *many* Roles whose
Permissions union ([CONTEXT.md](../../CONTEXT.md) — *User*, *Role*, *Permission*), and the side
nav is already built this way.

Twelve Cards ship, drawn only from features that have already shipped. Each Card lives in the
module that owns its data, is independently authorized on the API, and is composed onto one page
by the app shell — the same rule on both sides of the wire. No new module, no new migration, no
new permission, and no new domain rule is added by this phase.

Two supporting pieces make it demonstrable: the shared **`<DashboardCard>`** presentation
component (which every Card inherits, including its empty state), and an extension to the
seeders so the canonical role accounts — above all `multi@` — carry representative data.

Phase 5 and Phase 6 each contribute their own Cards as part of their own phase, so the Dashboard
grows with the system rather than needing a revisit. **Phase 7 (Reporting) keeps its original
position after Phases 5 and 6**; three of its eight reports (Academic Standing, Graduation
Readiness, Termination) depend on read models Phase 5 has not built yet.

## User Stories

**The landing surface**

1. As any authenticated User, I want the page I land on after signing in to summarise the work waiting for me, so that I know what to do without navigating through menus first.
2. As any authenticated User, I want each summary to link into the full screen behind it, so that the landing page is a starting point rather than a dead end.
3. As any authenticated User, I want to see only the summaries relevant to what I am allowed to do, so that the page is not padded with surfaces I cannot open.
4. As any authenticated User, I want a summary with nothing outstanding to tell me I am up to date, so that an empty state reads as good news rather than as a broken screen.
5. As any authenticated User, I want the landing page to stop showing me a list of my raw permission strings, so that the application looks like a product rather than a debugging tool.
6. As any authenticated User, I want my name and roles to remain visible on the landing page, so that I can confirm which account I am signed in as.
7. As a Student, I want my University ID to stay visible on the landing page, so that the identifier I need for every administrative interaction is always to hand.
8. As a User holding several Roles, I want one page showing the union of everything I am responsible for, so that I never have to switch personas to see my own work.
9. As a User holding several Roles, I want no "view as role" control to exist, so that there is no hidden mode in which I might be looking at an incomplete picture.
10. As any authenticated User, I want the landing page rendered in my chosen language with correct direction, so that Arabic is a first-class experience rather than a translation of an English layout.
11. As a User of assistive technology, I want the landing page to meet WCAG 2.1 AA, so that the most-visited screen in the application is usable by me.
12. As any authenticated User, I want one failing summary not to blank the whole page, so that a single broken query does not cost me the entire landing surface.

**Applicant (R01)**

13. As an Applicant, I want my application's current status on my landing page, so that I know where I stand without opening the application itself.
14. As an Applicant, I want to be told what I must do next, so that I am not left guessing whether the university or I am holding things up.
15. As an Applicant, I want a link straight into my application timeline, so that I can read the full history when the summary is not enough.

**Student (R02)**

16. As a Student, I want my CGPA on my landing page, so that the number my degree depends on is the first thing I see.
17. As a Student, I want my most recent Semester GPA beside my CGPA, so that I can tell whether I am improving.
18. As a Student with no graded Enrollment yet, I want my CGPA to render as a dash rather than 0.00, so that "not yet calculated" is never mistaken for "you scored zero".
19. As a Student, I want my current-semester registered credit hours summarised, so that I can see at a glance whether I am within the 6–12 CH limits.
20. As a Student, I want to know which registration period is currently open and when it closes, so that I do not miss an add/drop or withdrawal deadline.
21. As a Student, I want a link into my registration cart or my schedule depending on what is open, so that the landing page takes me to the action that is actually available.

**Advisor (R03)**

22. As an Advisor, I want the number of registration requests awaiting my approval, so that I know whether my advisees are blocked on me.
23. As an Advisor, I want to see the first few waiting requests by student name, so that the count becomes actionable rather than abstract.
24. As an Advisor, I want to see how many advisees I hold, so that I have a sense of my overall load.
25. As an Advisor, I want my summary scoped strictly to my own advisees, so that I never see another Advisor's queue.

**Instructor (R04)**

26. As an Instructor, I want to see my Course Offerings for the current semester, so that my teaching load is visible on landing.
27. As an Instructor, I want to know which of my Grade Sheets are not yet finalized, so that I can tell what still needs my attention.
28. As an Instructor, I want to be told when a grade-submission deadline has passed for one of my Offerings, so that the existing flag reaches me without my going looking for it.
29. As an Instructor, I want my outstanding Incompletes and the nearest resolution deadline, so that I can resolve them before they convert to Fail Withdrawals.

**Student Affairs (R05)**

30. As a Student Affairs officer, I want the count of applications pending screening on my landing page, so that SRS FR-ADM-005.5 is finally satisfied.
31. As a Student Affairs officer, I want overdue applications distinguished from merely pending ones, so that the screening SLA is visible rather than notional.
32. As a Student Affairs officer, I want to see the oldest waiting applications by name and age, so that I can start with the most urgent.
33. As a Student Affairs officer, I want my grade-entry surface summarised too, so that central grade entry is reachable from the same page as my admissions work.

**Department Head (R06)**

34. As a Department Head, I want the number of Grade Sheets awaiting my review, so that I know when Instructors are blocked on me.
35. As a Department Head, I want to see which Offerings those Sheets belong to, so that I can prioritise.
36. As a Department Head assigned university-wide, I want the department-less Preparatory Sheets included, so that my summary matches the review queue I actually hold.

**Dean (R07)**

37. As a Dean, I want the number of applications awaiting my decision, so that admissions decisions do not stall unnoticed.
38. As a Dean, I want that count scoped to my own Faculty, so that it reflects only what I have authority over.

**System / Super Administrator (R09, R10)**

39. As a System Administrator, I want a landing page about running the system, so that I am not shown every other role's work queue merely because I inherit every Permission.
40. As a System Administrator, I want counts of users and role assignments, so that I can see the shape of the system at a glance.
41. As a System Administrator, I want recent Audit Log activity summarised, so that security-relevant events are visible without opening a report.
42. As a System Administrator, I want the active Semester and which registration windows are open, so that I can confirm the calendar is configured as intended.
43. As a System Administrator, I want counts of active Programs and Courses, so that I can confirm the Catalog is populated.

**Demonstrability and correctness**

44. As the developer demonstrating the system, I want every canonical `rNN@` account to carry representative data, so that I can sign in as any role live without landing on an empty page.
45. As the developer demonstrating the system, I want `multi@` to hold both advisees and Offerings, so that the multi-role composition is actually demonstrable on the one account built to prove it.
46. As the developer, I want each Card's API endpoint independently authorized, so that the client's choice of what to request is never the security boundary.
47. As the developer, I want a test that asserts the *absence* of Cards a user should not see, so that a permission regression fails the build rather than leaking a surface.
48. As a future maintainer, I want the permission-to-Card mapping to be a declarative table, so that reading one file tells me who sees what.
49. As a future maintainer, I want a Card to exist only because a feature shipped it, so that the eight Permissions whose features arrive in Phases 5 and 6 produce no placeholder cards today.
50. As a future phase author, I want adding a Card to be a self-contained change inside my own module and feature, so that Phase 5 and Phase 6 extend the Dashboard without touching each other.

## Implementation Decisions

### Composition rule (ADR-0024)

- A Dashboard is **composed from the acting User's access**, never selected by a single Role.
  A User holding several Roles sees the **union** of their Cards on one page. There is no "view
  as role" control.
- **Default gate: Permission.** A Card is gated on exactly one Permission from the §3.2 matrix.
- **Established exception — persona Cards gate on Role.** A Card whose data hangs off a persona
  record the acting user may not possess (a Student anchor, an Applicant's Application) gates on
  the **Role**, not the inherited Permission. This is **not new**: it is the existing convention
  already documented in the frontend's shared access helper and applied since Phase 2 (ticket 11)
  and Phase 3 (ticket 04) — an administrator inherits `courses.register_self` and
  `grades.view_own` under [ADR-0008](../../docs/adr/0008-rbac-enums-static-matrix.md) but has no
  Student anchor, so a persona surface gated on the Permission would render a broken card.
  Phase 4.5 applies the existing rule to Cards rather than inventing a mechanism.
- **Administrators get an explicit Card set, not the union.** A User holding R09 or R10 sees the
  Administration Card plus the system-configuration Cards (Catalog Summary, Semester & Open
  Windows) and **no queue or persona Cards**. Holding every Permission is an authorization fact,
  not a description of an administrator's job. This reuses the existing admin-role helper and the
  `adminOnly` flag pattern the side nav already carries.
- **A Card exists because a feature shipped it.** Permission is the Card's *gate*, never its
  *trigger*. Eight Permissions in the enum (`standing.monitor`, the exemption and transfer/waiver
  pairs, the three transcript permissions) have no feature yet; they contribute no Card and no
  placeholder.

### The Card inventory

Twelve Cards. "Gate" is the authorization the SPA composes on and the API enforces.

| # | Card | Gate | Owning module | Content |
|---|---|---|---|---|
| 1 | My Application | Role R01 | Admissions | Application Status chip, next action, link to timeline |
| 2 | Screening Queue | `application.screen` | Admissions | pending count, overdue count, 3 oldest with age — closes FR-ADM-005.5 |
| 3 | Admission Decisions | `applicant.decide` | Admissions | Faculty-scoped count awaiting Dean decision |
| 4 | My Registration | Role R02 | Enrollment | current-semester CH total, open period, next deadline |
| 5 | Advisee Requests | `registration.approve` | Enrollment | pending request count, advisee count, 3 oldest |
| 6 | My Grade Sheets | `grades.enter` | Grading | this semester's Offerings, count not yet Finalized, overdue flag |
| 7 | Sheets Awaiting Review | `grades.finalize` | Grading | Department-Head queue count, 3 oldest |
| 8 | My Academic Record | Role R02 | Grading | CGPA, latest Semester GPA, earned CH |
| 9 | Outstanding Incompletes | `grades.enter` | Grading | count, nearest derived resolution deadline |
| 10 | Semester & Open Windows | `calendar.manage` | Calendar | active Semester, which windows are open, next date |
| 11 | Catalog Summary | `catalog.manage` | Catalog | active Program and Course counts |
| 12 | Administration | R09/R10 role | Identity | user and role-assignment counts, recent Audit Log activity |

Build order groups these as Tier 1 (1, 2, 4, 5, 6, 7, 8 — the demo path), Tier 2 (3, 9, 10, 11)
and Tier 3 (12).

### API contract

- **One endpoint per Card, namespaced under its owning module**, each independently authorized by
  the `can:` middleware (or the role check, for the persona and administration Cards). Routes are
  registered from the owning module's own `Public` route file, pointing at that module's
  `Internal` controller — the established pattern.

  *Clarification of the design session's shorthand:* the decision was recorded as "per-module
  panel endpoints, each `can:`-gated". A single endpoint per module cannot be `can:`-gated when
  that module owns Cards behind different Permissions (Admissions owns three, behind three gates),
  so the faithful implementation is one endpoint per Card. The load-bearing property — the
  endpoint lives in the module that owns the data, and each is independently authorized — is
  preserved exactly.

- **No new deptrac edges.** Every Card reads only its owning module's data through surfaces that
  module already has. No `Dashboard` module is created: a Dashboard is a view, not a bounded
  context, and it owns no domain language ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md)).
- **Responses use the standard `{data, meta}` envelope** via API Resources, and return **typed
  domain data only** — ids, codes, enum values, counts, dates. **Never pre-rendered display
  strings**: the SPA renders bilingually from the data, exactly as Notifications does
  ([CONTEXT.md](../../CONTEXT.md) — *Notification*).
- **Endpoints are read-only.** No Card endpoint mutates anything.
- Each new route carries a short first-line controller docblock (it becomes the operationId and
  therefore the Orval hook name) and an explicit `@response`. None of these routes take a model-bound
  parameter, so the `@urlParam` determinism rule does not apply — but the generated client must be
  regenerated and committed, or both `api:check` gates fail.
- Owner-local scalar counts are computed with aggregate queries rather than needlessly
  materialising collections. A Card may count an already-required materialised set when the rows
  are needed for business-day arithmetic, a cross-module Public-facade scope, or a bounded preview;
  it must not add a second query merely to count those same rows. Derived values (period state,
  attendance, Incomplete deadlines, GPA/CGPA) are read from the existing on-demand derivations and
  never recomputed or cached — the standing house rule.

### Frontend structure

- **A Card component lives in the feature that owns its data; the app shell composes.** This is
  forced by `eslint-plugin-boundaries` — a feature may not import another feature, and only `app`
  may compose anything — and it mirrors the API rule exactly: *owner supplies the Card, shell
  arranges it.* The direct precedent is the existing permission-filtered side nav, which already
  lives in the app shell for the same reason.
- **The landing page moves out of the auth feature into the app shell.** As a feature it cannot
  import other features, so it cannot compose Cards. The route stays `/`.
- **The permission-chip wall is removed.** A slim identity header (name, roles, and the Student's
  University ID block placed there by [ADR-0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md))
  is retained above the Card grid.
- **The permission-to-Card registry is a declarative table** in the app shell, alongside the
  existing nav table, carrying for each Card its key, its gate (permission, role, or admin-only),
  and its component. It is data, not logic — there is deliberately no function to unit-test.
- **`<DashboardCard>` is a new shared presentational component**: title, count slot, rows slot,
  empty-state slot, action link. It encodes the design *rules* and **no card names** — the same
  discipline `<StatusChip>` follows. Each feature supplies its own query hook, row renderer and
  bilingual copy.
- **Row rendering reuses `<StatusChip>`** wherever a row carries a status, giving the Cards visual
  continuity with the screens they link into.
- **Cards fetch independently and fail independently.** One Card erroring renders that Card's error
  state; the rest of the grid is unaffected.
- **The SPA chooses which Cards to request** from the roles and permissions already returned by the
  identity endpoint, which needs no change. This is a UX mirror only — the API re-authorizes every
  request and remains the sole authority
  ([ADR-0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)).
- **No new frontend dependencies.** Charts are explicitly rejected: a charting library would need
  its own RTL handling, dark-mode palette and axe treatment, and [design-system.md](../../docs/design-system.md)
  defines no chart tokens — a large surface bought for roughly one sparkline over a three-semester
  GPA history.
- **Every new string ships EN and AR** in its own feature namespace, per Card, as part of that
  Card's ticket. There is no deferred translation pass.

### Seeder

- Extend the existing seeders so the canonical role accounts carry representative data: `r02@` a
  Student with Enrollments and finalized Grades, `r03@` an Advisor with advisees and at least one
  pending registration request.
- **`multi@` receives both advisees and Course Offerings**, making the multi-role union
  demonstrable on the one account built to prove it.
- Seeders remain deterministic and idempotent, with every window a relative offset from seed time,
  and must continue to reserve the existing persona namespace so the Playwright suites' own data
  cannot collide.

### Glossary

Two entries are added to [CONTEXT.md](../../CONTEXT.md):

- **Dashboard** — the authenticated landing surface, composed from the acting User's access rather
  than selected by a single Role; a User holding several Roles sees the union of their Cards on one
  page, and there is no "view as role" switch.
- **Dashboard Card** — one summary on the Dashboard, owned by the module whose data it summarises,
  gated on a single Permission or Role, showing a count or short list plus a link into the full
  surface. A Card introduces no domain logic of its own.

**The term is "Dashboard Card", not "Panel".** "Panel" is already in use across the codebase as a
generic word for a bordered page section (interview, curriculum, version-history and cart panels
all ship today); adopting it as a domain term would put four existing components in violation of
the ubiquitous language, and renaming them is outside this phase's Definition of Done.

The Card/Report distinction is recorded explicitly so Phase 7 does not re-litigate it: a **Card**
is fixed, scoped to the acting user, and summary; a **Report** (SRS §4.12) is filterable,
parameterised and cross-student. Catalog Summary (Card 11) and Phase 7's Enrollment Summary report
are different objects.

## Testing Decisions

A good test here asserts **externally observable behaviour**: what an HTTP client receives for a
given authenticated persona, and what a user sees rendered for a given access set. No test reaches
below the HTTP boundary on the backend or asserts component internals on the frontend. Card counts
are asserted against **seeded data**, so a seeder regression fails a test rather than silently
changing a demo.

**Four seams, all of which already exist. This phase introduces no new seam.**

1. **Pest feature tests, per Card endpoint** — the existing per-module feature-test layout. The
   highest backend seam; there are no unit tests in this phase because the phase adds no domain
   rule to unit-test. Each Card endpoint is covered for:
   - the authorized persona receiving the correct counts and rows for the seeded world;
   - **403 for a user lacking the gate** — the authority assertion;
   - scoping, where the Card is scoped: an Advisor's Card counts only their own advisees, a Dean's
     only their own Faculty, a department-scoped Department Head's only their own department while
     a university-wide Head also reaches the department-less Preparatory Sheets;
   - the empty case returning an empty result rather than an error.
   Prior art: the existing Admissions, Enrollment and Grading feature suites, which already
   establish the authenticate-as-persona-then-assert-payload pattern and the 403 assertions.

2. **Vitest + RTL on the landing page** — the composition mirror. Mocks the auth hook and asserts,
   for a given access set, which Cards render **and which do not**. Absence is asserted as hard as
   presence. Cases: each single-role persona; the **multi-role union**; the administrator set
   (Administration plus configuration Cards, and explicitly *not* the queue or persona Cards); and
   a persona Card correctly withheld from an administrator who inherits its Permission but holds no
   Student anchor. Prior art: the existing side-nav test, which already mocks the same auth hook and
   asserts permission-, role- and admin-filtered item sets — this is the same test applied to Cards.

3. **Vitest + RTL on `<DashboardCard>`** — its states: populated with a count and rows, empty
   (rendering the supplied empty state rather than a bare zero), error, and with and without an
   action link. One test inherited by twelve Cards. Prior art: the Phase-0.5 shared-component tests.

4. **One Playwright spec plus axe** — `dashboard.spec.ts`, the phase's headline smoke and the only
   seam proving the server gate and the client composition **agree**, against real logins and real
   data. Signs in as five accounts and asserts each one's Card set, presence and absence:
   a `student.*` persona; `r03@`; `r04@`; **`multi@`** (the union, on one page, with no role
   switcher — the single most important assertion in the phase); and `r09@` (the administration set
   only). axe runs on the landing page across light/dark × EN/AR, since it is now the most-viewed
   screen in the application.

   The suite is **serial by configuration and must stay that way** — it shares one database and one
   set of seeded accounts whose UI language is server-persisted — so a five-login spec is correct
   but should be budgeted for wall-clock.

Seams 2 and 4 overlap deliberately. [ADR-0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)
makes the client an explicit *mirror* of server rules; verifying a mirror means testing both faces,
cheaply and exhaustively at the component seam and end-to-end at the browser seam. The existing side
nav is already tested this way.

**One deliberate non-seam:** the permission-to-Card registry stays a declarative table, so there is
no selection function to unit-test. The table is the specification, and seam 2 reads it.

The existing Phase-0 headline smoke asserts the dashboard heading and must continue to pass across
the landing page's move and rewrite.

## Out of Scope

- **Phase 7 Reporting (SRS §4.12).** It keeps its original position after Phases 5 and 6. Three of
  its eight reports — Academic Standing, Graduation Readiness, Termination — depend on read models
  Phase 5 has not built. A Card is not a Report; see the glossary distinction above.
- **Any new domain rule, migration, Permission, Role or module.** This is the phase's hard
  Definition-of-Done constraint, borrowed from Phase 0.5. Anything wanting a migration is out of
  scope by definition.
- **Cards for features that have not shipped** — Academic Standing, Credit-Hour Summary,
  exemption/transfer/waiver (Phase 5) and transcripts (Phase 6). Each of those phases adds its own
  Card as part of its own work. No placeholder cards.
- **Charts and any new frontend dependency**, including data-grid and charting packages.
- **Card personalisation** — reordering, hiding, pinning, or per-user layout preferences.
- **Real-time updates.** Cards refresh on page load and on the existing query-client rules; there is
  no websocket and no push, consistent with [ADR-0005](../../docs/adr/0005-localhost-learning-build.md).
- **Caching the Card payloads.** Derived values stay derived on demand so they cannot drift.
- **Global search**, still deferred from Phase 0.5.
- **Renaming the four existing `*Panel` components** to free the word "Panel".
- **Mobile-specific dashboard layouts** beyond the responsive behaviour the design system already
  provides.
- **The deptrac drift noted below**, which is recorded but not fixed here.

## Further Notes

- **This phase exists because of a gap, not a reordering.** The request that began the design
  session was to build Phase 7 before Phases 5 and 6. Investigation showed that Phase 7 was the
  wrong target twice over: three of its eight reports are hard-blocked on Phase 5, and the surface
  actually wanted was [SRS §2.3](../../docs/SRS.md)'s "role-based dashboards for all user types" —
  a scope bullet **the ROADMAP never assigned to any phase**. Phase 4.5 fills that gap; the
  original phase order survives intact.
- **The phase's name deliberately drops the word "role-based"** used by the SRS and
  architecture.md, because the composition rule is not role selection. ADR-0024 exists largely to
  record why the code disagrees with those two documents, so a future reader finding
  permission-composed Cards and no role switcher does not read it as an oversight.
- **Every ticket is a shippable stopping point.** The build order — shared component and page move
  with one Card end-to-end, then the seeder, then Tier 1, then Tier 3, then Tier 2, then the smoke
  and axe pass — means the phase degrades gracefully against a fixed date: after the first ticket
  there is a working Dashboard, and after Tier 1 there is a complete demonstration path.
- **The seeder ticket is placed second, not last, on purpose.** Cards built against empty accounts
  are how a Card that renders only zeros reaches a demo.
- **The Arabic copy for twelve Cards is the most easily underestimated line item** in the phase.
  It is per-Card work, in each Card's own ticket, and it is not optional: bilingual EN+AR is a
  cross-cutting decision held since Phase 0.
- **Documents to update alongside the tickets:** [CONTEXT.md](../../CONTEXT.md) (the two glossary
  entries), [ROADMAP.md](../../docs/ROADMAP.md) (a new §3 Phase 4.5 section, plus a note on the
  Phase 7 section recording its Phase-5 dependency), the new ADR-0024, and
  [HANDOFF.md](../../HANDOFF.md), which is stale: it still records Phase 4 as awaiting its review
  gate on a branch, though it merged to `main`, and still lists Phase 5 as the immediate next step.
- **Unrelated drift, recorded not fixed:** the deptrac ruleset's `App` layer lists five module
  `Public` layers but not Admissions or Notifications, though the ROADMAP records Phase 2 as having
  added those edges. It does not affect this phase — Card endpoints live inside their owning
  modules — but it is worth a look when something next needs them.
