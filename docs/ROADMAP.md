# Build Roadmap — Postgraduate Registration System (PRS)

**Egypt-Japan University of Science and Technology (E-JUST)**

| | |
|---|---|
| **Status** | Agreed plan, v1.0 (from a `/grill-with-docs` alignment session) |
| **Scope decision** | [adr/0005-localhost-learning-build.md](./adr/0005-localhost-learning-build.md) |
| **Source of truth** | [SRS.md](./SRS.md) · [business-requirements.md](./business-requirements.md) · [architecture.md](./architecture.md) · [tech-stack.md](./tech-stack.md) · [design-system.md](./design-system.md) |
| **Ubiquitous language** | [../CONTEXT.md](../CONTEXT.md) |

This roadmap turns the SRS/architecture into a **phased build plan**. It records the
scope and sequencing decisions made during alignment, and defines what each phase
delivers. It is a *plan*, not a spec — the SRS remains the requirements source of truth.

---

## 1. Build scope

This is a **localhost learning build**: the goal is to implement the PRS **domain and
workflow logic** faithfully and run it on a developer machine — *not* to operate a
production system. The SRS is labelled "Production Release"; we deliberately depart from
that. The full rationale and the explicit defer/retain lists are in
[ADR-0005](./adr/0005-localhost-learning-build.md).

**Kept:** two-tier React SPA + headless Laravel REST API (ADR-0001), modular-monolith
seams + deptrac (ADR-0002), PostgreSQL (ADR-0003), full RBAC (R01–R10 + permission
matrix), in-process domain events, and **bilingual Arabic/English + RTL from Phase 0**.

**Deferred / stubbed:** K8s/HPA, on-prem hosting, Redis, MinIO, Reverb, ClamAV,
Vault/KES, Horizon, MFA, SSO/LDAP/SAML, account lockout/CAPTCHA/password-policy,
email/SMS, cron scheduler, sync replica, and all scale/HA NFRs. See ADR-0005.

### Localhost footprint

- **DB:** PostgreSQL (dev), so the GPA/standing window-function math stays honest.
- **No** Redis / MinIO / Reverb / ClamAV / Vault / Horizon.
- **Files:** uploaded to local disk (Laravel `local` filesystem), no malware scan.
- **Derived read models** (Credit-Hour Summary, Academic Standing): computed
  **on-demand** from authoritative records — not cached, so they cannot drift.
  (Redis caching is a documented later optimization, not built now.)
- **Scheduled work** (Incomplete→FW conversion, SLA aging, reminders): implemented as
  **artisan console commands run manually**, not real cron/scheduler.
- **Notifications:** in-app table + a **polled** bell. No email, SMS, or websocket.
- **Auth:** Sanctum cookie-session, email+password, seeded users. Hardening deferred.

---

## 2. Cross-cutting decisions

| Area | Decision | Source |
|------|----------|--------|
| Quality bar | Localhost learning build; production NFRs deferred | Q1 → ADR-0005 |
| App shape | Two-tier (React SPA + Laravel REST API), domain-weighted vertical slices | Q2 (ADR-0001) |
| Auth | Minimal Sanctum session + **full RBAC**; hardening deferred | Q3 |
| Events / notifications | In-process events kept; user-facing = in-app table + polled bell | Q4 |
| Derived models | Computed on-demand (no cache) | derived |
| i18n | **Fully bilingual EN+AR + RTL from Phase 0** (`react-i18next` + MUI direction) | Q6 |
| UI / design system | **MUI retained** (Ant/shadcn rejected); neutral-led palette, E-JUST red as accent, light+dark, token-driven; built in **Phase 0.5** | [ADR-0009](./adr/0009-mui-ui-foundation-design-system.md) · [design-system.md](./design-system.md) |
| Sequencing | **Student-lifecycle order** | Q5 |
| Testing / DoD | Domain-TDD (Pest) + API feature tests + 1 Playwright smoke/phase + static-analysis gates | Q9 |
| Execution | ROADMAP now; per phase `/to-prd → /to-issues → /tdd → review`; **one phase at a time** | Q10 |

---

## 3. Phases

Each phase is a **vertical slice**: backend domain logic + `/api/v1` endpoints + a
minimal bilingual MUI UI + tests. A phase is **done** when its domain rules are covered
by test-first Pest tests, its headline flow has a Playwright smoke, and
deptrac + PHPStan/Larastan + eslint-boundaries + Pint are green (see §4). Build order is
strict; do not start a phase before the previous one's review gate passes.

### Phase 0 — Walking skeleton & foundations
The thinnest end-to-end slice that proves the whole stack. Runtime is **Laravel 12 /
PHP 8.4** ([ADR-0006](./adr/0006-runtime-baseline-laravel-12-php-84.md)). The decisions
below were sharpened in a `/grill-with-docs` session; `/to-prd` turns them into the phase PRD.

- **Monorepo:** `backend/` (Laravel 12) + `frontend/` (React + TS + Vite + MUI), beside `docs/`.
- **Modules:** hand-rolled PSR-4 (`Modules\` → `backend/modules/`) with one thin
  auto-discovered `ModuleServiceProvider` per module
  ([ADR-0007](./adr/0007-hand-rolled-psr4-modules.md)). **deptrac** governs the
  `Public`/`Internal` seam generically; only **Identity** is scaffolded now (later modules
  join the ruleset as born — first cross-module boundary test in Phase 1).
  `eslint-plugin-boundaries` for the frontend feature seams.
- **Identity & Access:** Sanctum SPA cookie auth (`login`/`logout`); seeded users (one per
  role R01–R10 + one deliberately multi-role); the R01–R10 role model + §3.2 permission
  matrix as **PHP enums + a static matrix** ([ADR-0008](./adr/0008-rbac-enums-static-matrix.md)),
  with `Gate`s registered from the matrix, API policy middleware + SPA route guards.
  `GET /api/v1/me` returns user + roles + effective permissions (the server is the sole
  authority; the SPA mirrors for UX only). Append-only **audit-log** table behind an
  `AuditLogger` (Identity/Public) that writes login/logout/failed-login now (account
  lockout stays deferred — a failed-login audit row is not lockout).
- **API conventions:** `/api/v1`; `{data, meta}` success via API Resources; native
  `{message, errors}` errors; standard status codes (422/401/403/404/409).
- **OpenAPI → typed client:** Scribe → OpenAPI 3.0 → **Orval** (TanStack Query hooks +
  Axios + Zod). `openapi.json` and the generated client are committed; both gates fail on drift.
- **i18n (full loop):** `react-i18next`, EN+AR per-feature namespaces + a shared `common`,
  MUI RTL/LTR flip; language persisted to `users.preferred_locale` via `PATCH /api/v1/me`;
  an `Accept-Language` middleware sets the Laravel locale so server messages localize too.
- **Dev environment:** Vite dev proxy (`/api` + `/sanctum` → Laravel, same-origin);
  **docker-compose runs Postgres only** (orchestration stays deferred per ADR-0005).
- **Gates:** `composer gate` (Pint, PHPStan/Larastan, deptrac, Pest) + `npm run gate`
  (ESLint + boundaries, Prettier, `tsc`, Vitest + RTL, one Playwright smoke) + a thin
  `.github/workflows/ci.yml` that calls both.
- **Demo:** log in as any seeded role → land on one guarded dashboard showing your
  identity, roles, and effective permissions, with a permission-gated widget and a live
  EN↔AR / RTL toggle.

### Phase 0.5 — Design System & Theming
A **frontend-weighted foundation slice** that turns the agreed design language into a themed,
tokenised MUI setup every later phase consumes. It sits **after Phase 0's review gate and
before Phase 1** because Phase 1's catalog TreeView is the first thing built on it. The full
spec is [design-system.md](./design-system.md); the reasoning + rejected alternatives (Ant
Design, shadcn/ui, brand-forward palette, Style Dictionary) are in
[ADR-0009](./adr/0009-mui-ui-foundation-design-system.md).

The implementation decisions below were sharpened in a `/grill-with-docs` session; the
theming architecture and reconciled wording live in [design-system.md](./design-system.md).

- **Tokens:** `frontend/src/app/theme/tokens.ts` holds **all** design values (colour ramps +
  semantic roles, type scale, spacing, radius, shadow, motion) as **MUI-agnostic typed
  data**; a thin `createAppTheme(direction, lang)` **adapter** maps them into
  `createTheme({ cssVariables: true, colorSchemes: { light, dark } })`.
- **Theming architecture (two axes):** light/dark flips the `data-mui-color-scheme`
  attribute via `useColorScheme` (`defaultMode: 'system'`, localStorage) — a **CSS-variable
  flip, no theme recreation**; `InitColorSchemeScript` in `index.html` prevents the
  first-paint flash. Direction + font **recreate the theme + swap the Emotion cache**
  (Phase-0 mechanism), memoised on **language only**. See design-system.md §3.5.
- **Palette:** neutral-led; **E-JUST red `#E4002B`** (50–900 ramp) as a disciplined accent
  with the accessible `#B00020` for red text; slate-blue secondary; semantic
  success/warning/error/info kept distinct from brand red. **Light + dark both ship.**
- **Typography:** **IBM Plex Sans + IBM Plex Sans Arabic** self-hosted via `@fontsource`
  (per-subset: latin / arabic), weights 400/500/600/700, `font-display: swap`, **preload**
  the two hot weights of the active language; family switched by language (extends
  [AppThemeProvider](../frontend/src/app/AppThemeProvider.tsx)), Arabic leading bump,
  `textTransform: none` on buttons, tabular figures for data.
- **Shape/spacing:** 8px spacing scale, 8px default radius, **flat/border-led elevation**.
- **App-shell chrome:** build the reusable **app-bar + role-aware side nav** now, wired from
  `GET /me` (brand, page title, language switcher, theme toggle, user menu, permission-
  filtered nav). **Global search + the notifications bell are deferred** to the phases that
  add their endpoints (they need data that doesn't exist; DoD forbids new backend logic).
- **Re-skin:** existing Phase 0 screens (login, dashboard, layout, language switcher) moved
  onto tokens — no raw hex left, **enforced by an ESLint `no-restricted-syntax` hex rule**
  (raw spacing/radius stay review discipline).
- **`<StatusChip>`:** a **presentational** `shared/ui` component (`tone`/`variant`/`icon`/
  `label`) encoding the design *rules* but **no status names**; each later phase colocates
  its own `statusToChip` map. The `/style` gallery renders the full §10 catalogue as demo
  data. Colour is never the only signal (icon/label always present).
- **Logo on dark:** white-chip approach, **no new assets** (no transparent/vector logo
  exists yet); `favicon.svg`/`icons.svg` placeholders + a transparent-logo swap are flagged
  as non-blocking cleanups (design-system.md §1).
- **`/style` gallery:** a **dev-only** route (`import.meta.env.DEV`-gated) showing palette,
  type scale, and key components across light/dark × EN/AR — living reference and the target
  of the Phase 0.5 Playwright smoke.
- **New deps:** `@mui/icons-material`, `@fontsource/ibm-plex-sans`,
  `@fontsource/ibm-plex-sans-arabic`, `@axe-core/playwright`. *(Not* `@mui/x-tree-view` /
  `@mui/x-data-grid` — those arrive with Phase 1 / Phase 7.)*
- **DoD:** **no new backend domain logic** (the one legitimately frontend-only slice — no
  Pest domain tests). Gates green (ESLint + boundaries + **hex rule**, Prettier, `tsc`,
  **Vitest + RTL incl. a token-contrast unit test**, and a **hybrid Playwright smoke** —
  functional assertions across all four combos as the gate + per-combo screenshots uploaded
  as CI artifacts, not pixel-asserted). WCAG AA is a **real gate**: the contrast unit test +
  **`@axe-core/playwright`** on `/style` and the re-skinned screens.
- **Demo:** toggle light↔dark and EN↔AR on the re-skinned dashboard; open `/style` and see
  the whole system render correctly in all four combinations.

### Phase 1 — Catalog (SRS §4.3)
The design below was sharpened in a `/grill-with-docs` session; the resolved glossary
lives in [CONTEXT.md](../CONTEXT.md) and the two hard-to-reverse choices in
[ADR-0010](./adr/0010-catalog-curriculum-model.md) (data model) and
[ADR-0011](./adr/0011-catalog-version-snapshots.md) (version history).

- **New `Catalog` module** (`Public` routes + Resources / `Internal` everything). First
  real **cross-module deptrac edge**: `Catalog → Identity\Public` (permissions/Gates)
  allowed, `Internal` private. The cross-module `CatalogService` facade is deferred to
  Phase 3 (built against Enrollment, its first consumer).
- **Model** (ADR-0010): Faculty → Department → Program; **Course** standalone with a
  **nullable** `department_id` (NULL = the shared university Preparatory courses);
  **Category lives on the Curriculum Entry** (`program_courses`), never on the Course, so
  a Course can be Core in its Program and an external elective for another. Program stores
  **required CH per Category** (default prep 6 / core 6 / elective 9 = 21). Bilingual
  EN+AR as **paired columns**, both required. `Lecture+Tutorial+Lab = Total` invariant;
  `DegreeLevel`/`CourseLevel` enums (BR-26); deactivate-only (no hard delete, BR-18).
- **Version history** (ADR-0011): append-only `catalog_versions` snapshots of the full
  aggregate on every manage action; read-only history API; distinct from the Audit Log.
- **CRUD + admin UI:** full backend CRUD/activate test-first, plus routed `features/catalog`
  admin pages; Curriculum edited on the **Program edit page** (three Category sections +
  course-picker). Permissions already fixed: `catalog.view` / `catalog.manage`.
- **Interactive program tree view** (FR-PCM-002): one `GET /catalog/tree` read-model
  endpoint (`@mui/x-tree-view`), **client-side** search/filter, course-status `<StatusChip>`;
  clicking a course opens detail + version history.
- Realistic **E-JUST seeder** — FoE → Computer Engineering → MSc & PhD fully populated from
  the FR-PCM-002 example (shared PRE401–404 linked into every Program), other faculties
  shallow.
- **Deferred:** CSV/Excel import (FR-PCM-003.5); `research_type` PBL/Seminar (Phase 3);
  course equivalencies (Phase 5).
- **Demo:** browse the University→Faculty→Dept→Program→Course tree; open course details.

### Phase 2 — Admissions (SRS §4.2)
The design below was sharpened in a `/grill-with-docs` session; the resolved glossary lives
in [CONTEXT.md](../CONTEXT.md) and the two hard-to-reverse choices in
[ADR-0012](./adr/0012-cross-module-reads-by-id-and-dto.md) (cross-module reads by id + DTO,
no FK) and [ADR-0013](./adr/0013-acceptance-handoff-student-anchor.md) (acceptance hand-off
+ Student anchor owned by Identity). Full applicant→Student workflow, thin but complete.

- **Two new modules** (`Public`/`Internal` each): **Admissions** and **Notifications**.
  New deptrac edges: `Admissions → {Catalog, Identity, Notifications}\Public`,
  `App → {Admissions, Notifications}\Public`; every `Internal` stays sealed.
- **Public self-registration** (the first unauthenticated, rate-limited route): registering
  **is** starting the application — one atomic act minting a User (R01) + a `Draft`
  Application, **gated on an open Admission Cycle**. Laravel's built-in signed email
  verification, link **shown on screen + logged** (24h; resend ≤ 5/24h; pre-verified
  shortcut). Account hardening stays deferred (ADR-0005).
- **AdmissionCycle** (ADR-0012 consumer): a windowed **Intake Term** (Fall/Spring) + year,
  unique per year, managed under `ManageAcademicCalendar`. An Application belongs to one
  Cycle; one per `(national_id, cycle)`; **re-application allowed in a later Cycle**.
- **Application aggregate:** personal info is a **per-cycle snapshot** (typed columns +
  enums; `PreviousDegree` extended to BSc/MSc/PhD). Primary + optional alternative **Program
  referenced by opaque id via a `Catalog\Public` read DTO facade — no cross-module FK**
  (ADR-0012); qualification rule = same-or-one-step-up ladder. Hand-rolled **13-state**
  machine (`ApplicationStatus` enum + static transition map; illegal → 409). Append-only
  **`application_status_transitions`** (who/when/note) = the applicant timeline *and* the
  decision audit trail — distinct from the Identity Audit Log.
- **Documents** (FR-ADM-003): a `DocumentType` enum carries its own rules (required/
  conditional/optional, size cap, formats, cardinality); stored to the **private local
  disk** (no malware scan / encryption-at-rest, ADR-0005); ULID public identifier; served
  by an **authorized streaming route** (owner or `ScreenApplications`); conditional-required
  enforced at finalize.
- **Review pipeline:** SA screen (approve/return/reject) → Dean (**faculty-scoped**)
  approve-for-interview/reject → **slot-pool interviews** (`InterviewSlot` + `Interview`;
  interviewer authorised by assignment, not a new permission; one reschedule) → Dean
  decision. SLAs config-driven; aging **derived on-demand**; a **flag/notify-only** artisan
  command marks overdue (**business days**, Fri/Sat weekend); interview reminders (48h/24h)
  via a second artisan command.
- **Terminal decisions:** the Student mint fires **only** on the `Accepted` transition.
  `ConditionallyAccepted` (free-text conditions) and `Waitlisted` (position) are holding
  states the Dean later resolves to Accepted or Rejected.
- **Acceptance hand-off** (ADR-0013): `Application::accept()` calls `Identity\Public::admit()`
  **synchronously in the same transaction** — creates the thin **`Student` anchor** (owned by
  Identity, 1:1 with User; University ID + opaque program ref + enrollment term + advisor +
  identity snapshot), mints **`{year}-{programCode}-{NNNN}`**, **replaces R01→R02**, writes an
  Identity audit entry. `AdmissionAccepted` (the project's first in-process domain event) fires
  for side-effects (acceptance notification). **Advisor = least-loaded R03** (derived), pre-filled
  + overridable. **Automatic exemptions deferred to Phase 5** (inputs captured; the event is the hook).
- **Identity also gains:** the **Dean↔Faculty assignment** (opaque faculty ref) with a
  minimal **admin management UI — gated on the R09/R10 _role_** (first role-gated surface; no
  new permission), and the `Identity\Public` student/advisor read surface.
- **Notifications module:** in-app only, `type` + JSON payload rendered **bilingually** on the
  SPA, **polled bell** (fills the Phase-0.5 shell slot); no email/SMS/websocket.
  Events-for-acceptance, direct `Notifier::notify()` for routine notices.
- **Frontend:** `features/admissions` (multi-step wizard with **per-step save**, reuses the
  Phase-1 catalog tree + `StatusChip`, status timeline) + reviewer queues + `features/admin`
  assignments; polled bell.
- **DoD:** Pest feature-first + targeted units (transition map, University-ID gen,
  business-days, least-loaded, qualification, `DocumentType`); **one comprehensive multi-role
  Playwright smoke** (applicant → SA → Dean → interviewer → Student); deptrac + axe gates.
  Deterministic seeder: open **Fall 2026** cycle, one Application per status, 3–4 advisors,
  a Dean per Faculty (assignments seeded), interview slots, a sample document file.
- **Deferred:** real email/SMS; malware scan / encryption-at-rest; automatic exemptions
  (Phase 5); SLA auto-enforcement; the full academic calendar (§4.11); GPA-scale conversion.
- **Demo:** a stranger registers into the open cycle, completes the wizard, is screened by SA,
  approved by the faculty Dean, interviewed, and accepted → becomes a Student with a
  University ID, an advisor, and role R02.

### Phase 3 — Enrollment (SRS §4.4; §4.5/§4.6 as accounting values only)
The design below was sharpened in a `/grill-with-docs` session; the resolved glossary lives
in [CONTEXT.md](../CONTEXT.md) (Course Offering, Semester, Enrollment Classification,
Research Type, Class Session, Attendance) and the hard-to-reverse choices in
[ADR-0017](./adr/0017-offering-based-registration.md) (offering-based registration +
structural seeds), [ADR-0018](./adr/0018-calendar-module-semester.md) (Calendar module owns
the Semester), and [ADR-0019](./adr/0019-attendance-session-register.md) (attendance
register — a deliberate SRS extension).

- **Two new modules.** A supporting **`Calendar`** module owning **`Semester`** (Term
  Fall/Spring + year, unique, never deleted) with five typed, order-validated period dates
  (start/end, registration window, add/drop end, late-add end, withdrawal deadline);
  openness always derived; CRUD under `calendar.manage`; AdmissionCycle stays in Admissions,
  linked by natural key (ADR-0018). And the **`Enrollment`** domain module. New deptrac
  edges: `Enrollment_Internal → {Identity, Catalog, Calendar, Notifications}\Public`,
  `App → {Enrollment, Calendar}\Public`.
- **Course Offering** (ADR-0017): one Course taught in one Semester — Instructor, optional
  capacity, weekly meeting rows; unique (semester, course) *now*, relaxable to sections
  later; managed under `calendar.manage` beside Semesters. **Enrollments reference the
  Offering, never the Course** — the structural seed for future timetabling/LMS (both
  seeded structurally only; zero placeholder schema; rooms stay out per SRS §2.4).
- **`CourseStatus` ships with the Appendix-D values known in Phase 3**, but only **Regular,
  Retake, Audit, W** are reachable in that phase; drop = hard delete, no trace.
  FW/Incomplete/CGPA-Improvement arrive with Phase 4. **Amended by
  [ADR-0030](./adr/0030-credit-decisions-are-not-enrollments.md):** Transfer, Waiver, and
  Exemption are standalone course-targeted records, not Course Statuses.
- **Registration workflow:** `RegistrationRequest` = a batch cart (min-6 CH is batch-level;
  no server-side Draft), lines referencing Offerings and carrying requested mode
  Regular/Audit (**Retake is always derived**). Advisor **"modify" = line-item trim, never
  add**; Phase 3 request statuses Pending/Approved/Rejected; approval mints Enrollments
  atomically (classification + registration-time total CH snapshotted per line, ADR-0017).
  **Amended in Phase 5 by ADR-0042:** existing `Pending` is displayed as “Pending Advisor,”
  and a full Must-Retake exception adds `AwaitingCapacityOverride` before the Department
  Head's final Approved/Rejected decision.
- **Period mechanics** (FR-REG-004): weeks 1–2 free add/drop as direct actions — but only
  *after* an approved initial request (advisor notified, no veto); week 3 = add-only via a
  `LateAdd`-kind request through the advisor; withdrawal self-serve until the deadline
  (sets W, no CH floor); after the deadline nothing (Phase 4's business). Refunds ignored
  (§2.4). External electives can never be free-added (BR-24 demands advisor approval).
- **Eligibility & classification** (BR-23–26): eligible list = own Curriculum ∩ this
  semester's Offerings (+ other programs' curriculum courses as external electives).
  Five-way **Enrollment Classification** (Prep / Core / Internal Elective / External
  Elective / Research Course) with level rules per degree; **`research_type` {PBL, Seminar}
  added to Catalog Course** (the Phase 1 deferral); composition enforced as registration
  caps — **external ≤ 1 (allowance), research course = exactly 1, type-matched** — full
  6/6/9 accounting stays Phase 5.
- **CH limits:** min 6 / max 12 via `config/enrollment.php`, one validator everywhere the
  schedule changes; Retakes + Audits count toward load. **First-semester exception**
  proxied now by enrollment-term match (tightens to exempted-only in Phase 5);
  **approaching-graduation exception strictly deferred to Phase 5** (needs Remaining CH);
  no advisor override hatch.
- **Retake rules:** one enrollment per (student, course, semester); a prior for-credit
  attempt derives `Retake` (max 1); **W/AU attempts don't burn the retake**. Grade gates
  (below-C+, prep/core same-course, CGPA-Improvement routing, the failed-external freeing
  the external cap) are documented **Phase 4 hooks**, not built.
- **Attendance** (ADR-0019, SRS extension): `ClassSession` rows generated from meeting
  times, instructor-cancellable (the holiday answer); binary marks by the assigned
  Instructor (authorization by assignment — matrix untouched); percentage always derived;
  **flag + notify only** (audit-at-risk < 75%), never an automated status change.
- **Cross-module seams:** Catalog finally grows the deferred facade — `CourseCatalog` +
  `CurriculumCatalog` (+ `CourseCategory`/`CourseLevel` promoted to Public); opaque ids per
  ADR-0012. Advisor queue reads **live advisees** via new
  `Identity\Public::studentIdsAdvisedBy()` — open requests follow reassignment.
  `Enrollment\Public` ships **empty** (Phase 4 builds the roster surface against grading).
- **RBAC:** existing permissions only (`RegisterCoursesSelf` / `RegisterCoursesForStudent` /
  `ApproveCourseRegistration` / `ManageAcademicCalendar` — all in the enum since Phase 0).
  **Register-for-student ships minimally:** SA for any student → Pending as usual; an
  Advisor for their advisee → **born Approved** (composer is the approver). ADR-0015 admin
  override applies to the new scope guards; admin writes audited.
- **Notifications:** five new types — `registration.submitted` (advisor),
  `registration.decided` (student), `enrollment.adjusted` + `enrollment.withdrawn`
  (advisor), `attendance.audit_at_risk` (student, fires on the crossing). No SLA/reminder
  commands (no SRS registration SLA).
- **Frontend:** new `features/enrollment` — student Registration cart (classification
  groups, live CH total, ADR-0014 client mirror) + My Schedule (StatusChips, attendance %,
  period-gated actions); advisor queue/review (line-item) + compose-for-advisee;
  instructor My Offerings → roster + attendance register; admin Semesters + Offerings
  screens (new instructor-list endpoint, the advisors-list precedent). New nav
  **enrollment section**; no new frontend deps.
- **DoD:** Pest feature-first + targeted units (CH validator + exceptions, 5-way
  classifier + caps, retake derivation, meeting overlap, session generation, attendance %
  + crossing, semester date ordering); **one multi-role Playwright smoke**
  (admin → student → advisor → instructor, incl. withdraw + audit-at-risk) + axe; Scribe
  `@urlParam` examples on all model-bound routes. Deterministic seeder: **two out-of-phase
  semesters** (Spring 2026 running & in withdrawal window; Fall 2026 registration open —
  windows relative to seed time), offerings over the Phase 1 anchor programs, students in
  varied states (pending request, W, derived Retake, AU below 75%).
- **Deferred:** grade-gated retake rules + CGPA-Improvement + external-cap freeing
  (Phase 4); approaching-graduation exception, exemption/transfer/waiver workflows +
  composition accounting (Phase 5); grade-submission-deadline/exam-period/holiday columns
  (their consumers' phases); rooms/timetabling engine + LMS features (structural seeds
  only, ADR-0017); registration SLAs (no requirement).
- **Demo:** admin opens Fall 2026 offerings; a Student builds a cart, the Advisor trims a
  line and approves; the Student free-drops one course, withdraws from a running Spring
  course (W chip), and the Instructor cancels a session and marks a register until an
  audit-at-risk flag appears.

### Phase 4 — Grading & GPA/CGPA (SRS §4.7; §4.4.6's grade-gated rules, deferred from Phase 3)
The design below was sharpened in a `/grill-with-docs` session; the resolved glossary lives in
[CONTEXT.md](../CONTEXT.md) (Grade, Mark, Letter Grade, Grade Sheet, Attempt, Incomplete, Fail
Withdrawal, CGPA Improvement, Quality Points) and the hard-to-reverse choices in
[ADR-0020](./adr/0020-grading-owns-gpa-read-model.md) (Grading owns the GPA/CGPA read model,
amending [architecture.md](./architecture.md) §5),
[ADR-0021](./adr/0021-grading-scale-uniform-bands.md) (a uniform grading scale replacing SRS
Appendix A's non-contiguous bands),
[ADR-0022](./adr/0022-grade-outcome-pushed-to-enrollment.md) (the `earned` bit pushed across
the seam to keep `Grading → Enrollment` one-way) and
[ADR-0023](./adr/0023-grade-sheet-submission-rounds.md) (the Grade Sheet is a submission
round). **§5's GPA/CGPA agenda is closed by this phase** — see below.

- **New `Grading` module** owning the Grade, the Grade Sheet, the Incomplete and the GPA/CGPA
  read model (ADR-0020 — *not* Records, which is a Phase-6 document-issuance context). New
  deptrac edges: `Grading_Internal → {Identity, Catalog, Calendar, Enrollment,
  Notifications}\Public`, `App → Grading\Public`.
- **`Enrollment\Public` finally ships** (ADR-0017's deferral, now due): roster and Enrollment
  reads as DTOs with opaque ids, plus four commands (`recordOutcome`, `markIncomplete`,
  `resolveIncomplete`, `convertIncompleteToFailWithdrawal`) so **Enrollment stays the sole
  writer of `course_status`**. The dependency runs **one way** (ADR-0022): at finalize Grading
  pushes a single nullable `earned` boolean — "this Attempt reached C+" — so Enrollment's
  registration gates stay pure functions over its own rows and there is no `Public↔Public` cycle.
- **The scale** (ADR-0021): nine letters A+…F as a `LetterGrade` enum carrying its own points
  and band — uniform five-point bands, total over 0–100, **replacing** Appendix A's
  non-contiguous ones (which left 91 unrepresentable) and moving the minimum pass from 77% to
  75%. No P/FL, no A−/B−/C−. One **Mark** per Enrollment; letter and points derived and
  **frozen** onto the Grade, so a later scale change never rewrites a grade already given.
- **Grade Sheet = one submission round for one Offering** (ADR-0023), created lazily on first
  open: `Draft → Submitted → Finalized`, or `Submitted → Returned → Draft`; kinds Initial /
  IncompleteResolution / Correction; illegal transition = **409** (the Admissions precedent).
  A Finalized round is **never reopened** — correcting it means a Correction round carrying only
  the affected lines. **Supersession is derived, never stored:** the current Grade is the one on
  the latest Finalized Sheet, and the earlier one stays visible as history.
- **Routing and authority:** a Sheet routes to the Department Head of its Course's Department
  via a new Identity **Department-Head assignment** whose department reference is **nullable** —
  NULL being the university-wide scope, so the department-less Preparatory Courses route with
  **no special case** (ADR-0010). The §3.2 matrix is **untouched**: entry is `grades.enter`
  (Instructor + SA), signing and review are `grades.finalize`, and *which* Sheet is authorised by
  assignment. SA keys in marks centrally but can never sign; every such write is audited.
- **GPA/CGPA — closing SRS §4.7.2:** `Σ(points × CH) ÷ Σ(CH)` over CGPA-counting,
  non-superseded, graded Enrollments; Semester GPA is the same sum restricted to a term. Counting
  is Phase 3's Appendix-D matrix, read never reimplemented; **earning** credit is a separate
  `≥ C+` test, so a D counts fully and earns nothing. Among a Course's Attempts only the
  chronologically latest by the Semester's **`starts_at`** counts (Fall precedes the following
  Spring) — unconditionally, so a worse Retake genuinely lowers the CGPA. Accumulated in integer
  hundredths and **rounded half-up to two decimals exactly once**, that one figure authoritative
  for display *and* every threshold. **No CGPA-counting graded Enrollment ⇒ `null`, not 0.00.**
  Computed **on demand, never stored or cached** (§1 — no Redis, so it cannot drift).
- **Incomplete** (SRS §4.7.3): declared on a Sheet line in place of a mark with a completion
  percentage (≥ 70, config-driven) and a reason — an attestation, not a measurement (ADR-0019).
  Its **deadline is derived, never stored**: the following Semester's `starts_at` + 63 days
  (week 10), so correcting a start date moves every outstanding deadline with it. Resolution is
  its own round landing in the *original* Semester's GPA. **FW is reachable only from an
  unresolved Incomplete** — no administrative late-withdrawal path, no Instructor-assigned FW.
- **The grade-gated registration rules Phase 3 documented as hooks become real:** one combined
  allowance of **at most two for-credit Attempts** ever; the second is a `Retake` below C+ and a
  `CgpaImprovement` at or above it; registration refused while an earlier Attempt is an
  unresolved Incomplete; a **failed external elective frees** the lifetime external allowance
  (FR-REG-006.4); `RetakeDeriver` renamed **`AttemptDeriver`**. FR-REG-006.5's "must retake" is a
  **visible marker, not a gate** in this phase; Phase 5 deliberately adds the enforcement
  rule in [ADR-0042](./adr/0042-must-retake-courses-have-registration-priority.md).
- **Calendar gains exactly one column**, the grade-submission deadline
  (`ends_at ≤ deadline`); exam-period/holiday columns stay deferred (ADR-0018 forbids
  anticipatory schema). **Catalog is unchanged.**
- **Three manually-run commands** (ADR-0005, the `admissions:flag-overdue` precedent) —
  flag-and-notify only, except the conversion the SRS mandates:
  `grading:convert-expired-incompletes` (the one that changes state; skips and *reports* an
  Incomplete whose next Semester does not exist yet), `grading:send-incomplete-reminders`
  (fires on an exact 28/14/7/1-day match, so no sent-marker is needed) and
  `grading:flag-late-sheets`.
- **Notifications:** seven new in-app types — sheet submitted/returned, grade finalized
  (carrying the letter *and* whether it fell below C+, so the must-retake warning needs no second
  type), Incomplete declared/reminder/converted, grades overdue. Advisors deliberately excluded
  (§4.8's advisor copy is Phase 5's standing module).
- **Frontend:** new `features/grading`, **no new dependencies** — the Instructor's sheet list and
  MUI-`Table` editor with a live client-mirrored letter preview (ADR-0014;
  `@mui/x-data-grid` stays deferred to Phase 7), outstanding Incompletes + resolution, the
  Department Head's queue and review (finalize / return with a note, plus the distribution),
  Student **My Grades**, Advisor/SA scoped oversight, and SA central entry reusing the same
  editor. New **grading nav section**; Department-Head assignment joins `features/admin`.
- **DoD:** Pest feature-first + targeted units (the scale at **every band boundary and both
  sides**, quality-points accumulation and half-up rounding including a case that rounds *across*
  a threshold, supersession across a Fall→Spring boundary, the Incomplete deadline arithmetic);
  **one multi-role Playwright smoke** (instructor grades + defers → head finalizes → student sees
  their GPA/CGPA as a **value** → the conversion command runs → the student sees the Fail
  Withdrawal) + axe; Scribe `@urlParam` examples on all model-bound routes. Deterministic seeder:
  **three completed Semesters behind Phase 3's pair** (windows relative to seed time) so an
  Incomplete is genuinely overdue on seed day, Sheets in every state incl. a Correction round,
  Department Heads per Department + one university-wide (and one Department deliberately
  uncovered), and Students carrying a superseded Retake, a must-retake prep grade, a corrected
  grade and a failed external elective.
- **Deferred:** academic standing/warnings + the Credit-Hour Summary, and
  exemption/transfer/waiver (Phase 5 — `GradeFinalized` is their hook); transcripts and the
  AU/W/FW/I grade-column projection (Phase 6); the GPA-distribution report (Phase 7); grade
  appeals as a student-initiated workflow; assessment components/weights/curves and letter
  overrides; blocking registration on an outstanding must-retake obligation (resolved for
  Phase 5 by ADR-0042); caching the CGPA.
- **Demo:** an Instructor opens a completed term's grade sheet, marks one student and defers
  another with a reason, and signs; the Department Head reads the distribution and finalizes;
  the student sees their letter, Semester GPA and CGPA; the conversion command runs and the
  deferred student's Incomplete has become a Fail Withdrawal.

### Phase 4.5 — Dashboards (SRS §2.3, FR-ADM-005.5)
A **composition slice** inserted between Phase 4 and Phase 5, exactly as Phase 0.5 was inserted
between Phase 0 and Phase 1 — Phases 5, 6 and 7 keep their numbers and order. It exists because
SRS §2.3's "role-based dashboards for all user types" is a scope bullet **this ROADMAP never
assigned to a phase**: four phases of domain machinery had shipped and every one of them was
reachable only by navigating into it, while the authenticated Dashboard was still Phase 0's
wall of raw permission chips. The composition rule is recorded in
[ADR-0024](./adr/0024-permission-composed-dashboard-cards.md); the two glossary entries
(*Dashboard*, *Dashboard Card*) are in [CONTEXT.md](../CONTEXT.md).

- **The composition rule (ADR-0024):** a Dashboard is composed from the acting **User's own
  access**, never selected by a single Role, and there is **no "view as role" switch** — a User
  holding several Roles sees the **union** of their Cards on one page. This deliberately departs
  from the wording of [SRS §2.3](./SRS.md) and [architecture.md](./architecture.md) ("each role
  sees a tailored dashboard"), because the system already models a User as holding many Roles
  whose Permissions union, and the side nav has been built this way since Phase 0.5.
- **The gates:** Permission by default; **Role for a persona Card** whose data hangs off a record
  the acting user may not possess (a Student anchor, an Applicant's Application) — the existing
  convention since Phase 2, not a new mechanism, since an administrator inherits every Permission
  under [ADR-0008](./adr/0008-rbac-enums-static-matrix.md) and holds none of those records.
  **Administrators get an explicit Card set**, not the union: Administration plus the two
  configuration Cards, and no queue or persona Card.
- **Twelve Cards**, each drawn only from a feature that had already shipped, each owned by the
  module that owns its data: My Application, Screening Queue (closing **SRS FR-ADM-005.5**, never
  built since Phase 2), Admission Decisions, My Registration, Advisee Requests, My Grade Sheets,
  Sheets Awaiting Review, My Academic Record, Outstanding Incompletes, Semester & Open Windows,
  Catalog Summary, Administration. **A Card exists because a feature shipped it** — Permission is
  a Card's gate, never its trigger, so the eight Permissions whose features arrive in Phases 5 and
  6 contribute no Card and no placeholder.
- **API:** **one read-only endpoint per Card**, namespaced under its owning module's own `Public`
  route file and **independently authorized** by its own `can:` (or role) gate — the client's
  choice of what to request is never the security boundary. Standard `{data, meta}` envelope,
  **typed domain values only** (ids, codes, enum values, counts, dates) and never pre-rendered
  display strings, so the SPA renders bilingually from the payload as Notifications does. Counts
  are aggregate queries; every derived figure (period state, Incomplete deadlines, GPA/CGPA) is
  read from the derivation that already owns it — never recomputed, never cached.
- **No new module:** a Dashboard is a view, not a bounded context, and owns no domain language
  ([ADR-0002](./adr/0002-modular-monolith-seams.md)) — so **no new deptrac edge** either.
- **Frontend:** the Dashboard **moves out of the auth feature into the app shell** (the route
  stays `/`), because `eslint-plugin-boundaries` forbids a feature importing another feature —
  the same reason the permission-filtered side nav lives there. Each Card component lives in the
  feature that owns its data and the shell composes them from a **declarative
  permission-to-Card registry** beside the existing nav table. New shared
  **`<DashboardCard>`** (title, count, rows, empty/loading/error states, action link) encoding the
  design rules and **no Card names**, the `<StatusChip>` discipline. Cards **fetch and fail
  independently**. The permission-chip wall is gone; a slim identity header keeps name, roles and
  the Student's University ID ([ADR-0013](./adr/0013-acceptance-handoff-student-anchor.md)).
  **No new frontend dependency** — charts explicitly rejected (no chart tokens exist, and RTL +
  dark + axe would all need inventing for roughly one sparkline).
- **Seeder:** the canonical `rNN@` accounts were authorization fixtures carrying no data, which is
  invisible while every screen is a list you navigate into and glaring the moment the Dashboard
  is made of counts. `r01@` gains a Draft Application, `r02@` becomes a real Student with two
  graded Semesters (a computable CGPA), `r03@` gains advisees with a pending request, and
  **`multi@` gains both advisees and Course
  Offerings** — the one account that demonstrates the composition rule. Still deterministic,
  idempotent, and inside the reserved `student.*` namespace.
- **DoD:** Pest feature tests per Card endpoint (authorized payload, **403 for the missing gate**,
  scoping, empty case) — no unit tests, because the phase adds no domain rule; Vitest + RTL on the
  page (which Cards render **and which do not**, incl. the multi-role union and the administrator
  set) and on `<DashboardCard>`'s states; **`dashboard.spec.ts`**, the headline smoke, signing in
  as seven seeded accounts and asserting each Card set presence *and* absence, with `multi@`'s
  union and the absence of any role switcher as its central assertion; **axe on the Dashboard
  across light/dark × EN/AR**. Also **fixed app-wide**: `MuiLink`'s default primary colour was the
  vivid brand red, ~3.8:1 on the dark surface — the first screen dense enough in links for a
  dark-mode scan to catch it (design-system.md §12 already named the accessible shade).
- **Constraint, borrowed from Phase 0.5:** **no new migration, Permission, Role, module or domain
  rule.** Anything wanting a migration was out of scope by definition.
- **Deferred:** Cards for features that have not shipped — Academic Standing and the Credit-Hour
  Summary (Phase 5), transcripts (Phase 6) — each arriving with its own phase, so the Dashboard
  grows with the system; charts and any new frontend dependency; Card personalisation (reorder,
  hide, pin, per-user layout); real-time updates (no websocket, ADR-0005); caching the Card
  payloads; global search (still deferred from Phase 0.5); renaming the four existing `*Panel`
  components to free the word "Panel"; mobile-specific dashboard layouts beyond the responsive
  behaviour the design system already gives. **Recorded, not fixed:** deptrac's `App` layer lists
  five module `Public` layers but neither Admissions nor Notifications, though Phase 2 records
  adding those edges.
- **Demo:** sign in as `multi@` and see one page carrying the advisee queue *and* the ungraded
  grade sheets, with no role switcher anywhere; then as `r09@` and see an administrator's own
  page rather than everyone else's work queues.

### Phase 4.6 — Query, Filter, Export & Reports (SRS §4.12, FR-PCM-003.5, NFR-PRF-010)
A **platform-plus-analytics slice**, inserted between Phase 4.5 and Phase 5 for one structural
reason: it changes the shape of **every list endpoint in the system**, and doing that before
Phases 5, 6 and 7 means their lists are *born* filterable and exportable rather than retrofitted
twice. It also closes five of SRS §4.12's eight reports, which turns out to be possible now — the
ROADMAP's claim that Phase 7 could not be pulled forward is true of only **three** of its reports
(Standing, Graduation Readiness, Termination), the ones needing Phase 5's read models. The four
hard-to-reverse choices are in [ADR-0025](./adr/0025-support-layer-query-contract.md) (the Support
layer, the paginated envelope, filterable-iff-readable),
[ADR-0026](./adr/0026-reports-owned-by-modules.md) (report ownership + gates),
[ADR-0027](./adr/0027-export-sibling-route-audited.md) (export) and
[ADR-0028](./adr/0028-aggregate-facades-sql-php-parity.md) (batched owner-facade derivation);
[ADR-0024](./adr/0024-permission-composed-dashboard-cards.md) is **amended** for what
a Card may now carry, and [CONTEXT.md](../CONTEXT.md) gains **Report** and **Export**.

- **A shared `Support` layer** (ADR-0025) — the project's first shared backend code, under
  `backend/support/`, declared to deptrac as its own layer with **zero outbound edges** and
  `*_Internal → Support` granted for every module. It is the backend mirror of `shared/ui`:
  the query rules and **no domain names**. Deliberately *not* a module — it owns no domain
  language, which is the objection ADR-0024 raised against a Dashboard module.
- **One query contract on every collection endpoint** — flat params
  (`?status=a,b&cgpa_min=3&q=…&sort=-cgpa,name&page=2&per_page=25`), including comma-separated
  multi-column sort plus a stable unique-key tie-breaker, and an **always-paginated** envelope
  (never conditional — two response shapes on one operation generate a TS union Orval cannot read),
  25 rows default / 100 for pickers / **hard cap 100**. "Every row" is `/export`, which is what
  makes the cap right rather than merely tolerable.
  Nested aggregate/history read models (Catalog tree, timeline, version history, curriculum) remain
  complete parent-object responses and are not treated as pageable/exportable collections.
- **Free-text `q`** — case-insensitive substring over a per-resource declared field list across
  **both `_en` and `_ar`** columns, Arabic **normalised on both sides** (alef forms → `ا`, `ى`→`ي`,
  `ة`→`ه`, diacritics stripped). Chosen over Postgres full-text, which gives Arabic no stemming
  (no dictionary) and cannot match `CSE5` against `CSE511`. `pg_trgm` recorded as the later
  optimization, as §1 records Redis caching.
- **Filterable iff readable** (ADR-0025) — a field is filterable/sortable only if the actor may
  **read** it, because `?cgpa_min=` plus `meta.total` is a CGPA oracle. Row-level scope stays a
  base query constraint the client never supplies; filters narrow within it and never widen it, and
  an out-of-scope value returns an empty page rather than a probeable 422.
- **Export** (ADR-0027) — `GET /{resource}/export` beside each list, same query string, **true
  `.xlsx` via `openspout`** (a 14-digit National ID stays text instead of `2.9001E+13`), a
  **canonical column set per resource server-rendered in the actor's locale** with RTL sheets for
  Arabic — the one recorded exception to ADR-0024's typed-values-only rule, because a spreadsheet
  has no client to render for. Authorized by the **list's own gate and scope**, adding no
  Permission (the Central Grade Entry reasoning), projected through a separate actor-aware export
  column contract, and **always written to the Audit Log** with
  actor, resource, criteria and row count: the new fact is not that data was read but that it left.
  Export is a List capability: every migrated collection gets a sibling unless its ticket records
  a security exclusion, Reports export through drill-through, and the SPA downloads through the
  authenticated Axios client into an in-memory blob rather than a bare link.
- **Derived values are batched through their owner** (ADR-0028) — candidate IDs/DTOs cross existing
  `Public` facades in bounded batches, owners bulk-read only their own tables, and the existing PHP
  calculators remain the single arithmetic implementation. Derived filtering and sorting happen
  before in-memory page slicing; stored fields still paginate in SQL. Batch-versus-single parity
  over the seeded cohort proves the facade shapes agree without cross-module joins or duplicate SQL.
- **Six Reports, each owned by the module owning its spine data** (ADR-0026), the SPA composing one
  Analytics section — *owner supplies, shell arranges*, ADR-0024's rule a third time, at the cost
  of **zero new deptrac edges**: Application Pipeline (Admissions), Enrollment Summary + Course
  Registration Summary + **Attendance Analytics** (Enrollment — an ADR-0019 extension §4.12 never
  listed), GPA Distribution (Grading), Audit Log (Identity — also the system's **first audit read
  surface**). Gated on the nearest data-read Permission, at least as strong as the list each drills
  into; every divergence from §4.12's role table is listed in the ADR.
- **Charts** — `@mui/x-charts` (same MUI X family as the installed tree view, so it inherits the
  theme, CSS variables, dark mode and the `direction` flip), drawing on
  [design-system.md](./design-system.md) **§14's already-written chart palette**. Every chart is
  `role="img"` plus a **"View as table"** disclosure rendering the identical series, with Vitest
  asserting the two cannot drift — the reason Phase 4.5 rejected charts (RTL + dark + axe unproven)
  is answered rather than waived.
- **Interactivity = parameters + drill-through** — a Report's parameters ride the same filter bar
  the grids use, and a chart segment **navigates to the filtered list behind it**, which exports.
  Filter/sort/page state lives in the **URL**, so a view is bookmarkable and shareable and the
  export action fetches the same query string from `/export` through authenticated Axios.
- **Frontend:** new `shared/ui` `<FilterBar>`, `<DataToolbar>`, pagination and a URL-state hook —
  the query rules and **no domain names**, the `<StatusChip>`/`<DashboardCard>` discipline —
  rendering into the plain MUI `<Table>` already in use (`@mui/x-data-grid` **still** not adopted:
  with filtering, sorting and paging server-side the grid is dumb, and the community tier's
  single-criterion filter panel is the thing being replaced). Plus a **column chooser** (screen
  only — never the file), a **print stylesheet** for Reports, and **global search** finally filling
  its Phase-0.5 app-bar slot as a **client-side fan-out** over each permitted module's own list
  endpoint at `?q=&per_page=5`, independently authorized and independently failing exactly as
  Cards are.
- **Richer Cards** (ADR-0024 amendment) — figures, term-over-term deltas and at most one chart over
  the acting user's own data. **A Card takes no parameter**; the moment it would, it is a Report.
- **Bulk course import** — closes **SRS FR-PCM-003.5**, deferred since Phase 1 and cheap now that
  the spreadsheet library is paid for: upload → **dry-run preview** (rows to create/update/reject,
  with per-row per-column reasons, writing nothing) → confirm applies the **whole file in one
  transaction or none**, writing one ADR-0011 version snapshot for every Course created or changed
  plus one Identity Audit Log event for the import transaction. The uploaded file is held on the private
  local disk under a ULID between the two calls, the
  [ADR-0016](./adr/0016-private-document-serving.md) pattern. The courses **export is the import
  template**, so the loop is export → edit → preview → apply. Gated on `catalog.manage`; both EN
  and AR names required per row, as the Catalog demands. MIME, size, workbook structure, enum, and
  credit-hour validation are mandatory; the importer recognizes both canonical English and Arabic
  header/enum dictionaries for round trips. Malware scanning remains explicitly deferred by ADR-0005.
- **Seeder:** every named persona **untouched** (the smokes assert against them), with a
  deterministic generated cohort of ~150–250 Students layered on top — spread across Programs,
  terms, grades and attendance from a fixed seed, in the reserved namespace — so a distribution has
  shape and pagination actually pages. Existing count-based assertions are audited as part of the
  change.
- **DoD:** the contract proven **exhaustively once** as `Support` unit tests (operators, sorting,
  paging, Arabic normalisation), then a shared **Pest dataset** of `(endpoint, actor, expected
  scope)` driving four assertions across every filterable endpoint — unknown fields rejected, scope
  composes with filter, actor-aware fields stay hidden, `meta` correct — so a new endpoint is one
  dataset row; **batch-versus-single parity** for derived facade results; one `.xlsx` read back end-to-end
  with openspout's reader (headers, a typed National-ID cell, RTL on the Arabic sheet) and row
  generators asserted for the rest; Vitest on `<FilterBar>`, URL state and every chart's
  table-matches-series; **one comprehensive Playwright smoke** (the Phase-2 multi-flow precedent)
  — filter a queue → export it → open Analytics → drill a chart segment back into that filtered
  queue — plus **axe on Analytics across light/dark × EN/AR**. `openapi.yaml` and the Orval client
  regenerate across every named collection endpoint; both `api:check` gates make that non-optional.
- **Deferred:** saved/named filters; cross-filtering between charts; client-side pivoting or an
  ad-hoc report builder; caching report aggregates and materialising any derived value (ADR-0028);
  real-time updates (no websocket, ADR-0005); `pg_trgm` indexes; scheduled or emailed reports (no
  email); PDF export of Reports (server-generated Transcript PDF is also explicitly
  deferred in Phase 6 under ADR-0045); Card
  personalisation (still, from Phase 4.5); the **three Phase-5-dependent reports** — Academic
  Standing, Graduation Readiness, Termination — which stay Phase 7. **Recorded, not fixed** (still,
  from Phase 4.5): deptrac's `App` layer is missing its `Admissions` and `Notifications` edges —
  and this phase does *not* force the fix, because global search is a client-side fan-out rather
  than a server-side composition.
- **Demo:** open the screening queue, narrow it by Program and applicant age and search an Arabic
  name unaccented, export the result and open it in Excel with the National ID intact; then open
  Analytics, read the GPA distribution for a Program, click the band below 3.0 and land on exactly
  those students, filtered — and export them too.

### Phase 5 — Academic Standing + Credit-Hour Summary + Exemption/Transfer/Waiver (SRS §4.8, §4.5, §4.6)

**Status: complete.** All 17 Phase-5 tickets are implemented and verified by the backend and frontend quality gates.

- **`AcademicStanding` module** owns both derived read models ([ADR-0029](./adr/0029-academic-standing-owns-progress-read-models.md)):
  per-category Credit-Hour Summary (Appendix D counting) and Academic Standing
  (Not-Yet-Assessed/Good/Warning/Probation/Termination-Pending), computed on demand from the owning
  modules' public facades. Phase 6 Records consumes this facade rather than recalculating
  either view. Each Student's requirements come from the admission-time Curriculum Version,
  never the Program's mutable current Curriculum. Existing Students bind to the latest
  Version at or before admission, falling back audibly to the earliest available Version
  when Catalog history starts later ([ADR-0034](./adr/0034-students-bind-to-admission-curriculum-version.md)).
  Each category exposes raw Baseline/Adjusted Required, Attended, Earned, Transferred,
  Waived and Exempted CH; Satisfied CH first allocates by Course target so an effective
  Exemption and later Earned CH for that same Course cannot both advance progress, then is
  capped at Adjusted Required, while Remaining CH is floored at zero and the Total row sums
  categories ([ADR-0035](./adr/0035-credit-hour-summary-accounting.md)).
  The approaching-graduation exception applies only at 1–5 Remaining CH when the proposed
  for-credit schedule satisfies every remaining target or slot; the facade therefore
  exposes unsatisfied requirements as well as numeric totals. Approaching Graduation is
  independent of CGPA and Termination Pending, whose separate blockers remain visible but
  do not suppress the completing schedule during Probation or due process. The
  first-semester proxy from
  Phase 3 is tightened to the same shape: a 1–5 CH load is allowed only in the enrollment
  Semester and only when it contains every remaining non-exempt Preparatory requirement;
  with none remaining, the normal floor applies.
  Standing is exhaustive under [ADR-0036](./adr/0036-exhaustive-academic-standing.md):
  Termination Pending is evaluated first even without a CGPA; otherwise no finalized
  CGPA-bearing Grade means Not Yet Assessed, `<3.00` is Probation, `≥3.00` plus any
  effective Grade below C+ is Warning, and `≥3.00` with all effective Grades at least C+
  is Good. Probation caps the total Semester load, including Audit, at the configured
  minimum CH; Termination Pending uses the same ceiling while due process continues, and
  the two approved below-minimum exceptions remain available. Only final termination blocks
  registration mutations. Append-only Standing Observations checkpoint actual changes for
  once-only adverse and recovery notifications, but never answer current Standing reads;
  idempotent event handlers and the manually run
  `academic-standing:reconcile {student?}` command share one per-Student service. A global
  run evaluates time-based ordinary/remediation checkpoints for every active Student; a
  scoped run repairs one Student. The service opens or clears Termination Cases, appends
  Standing Observations and once-only notifications, and reads without converting
  Grading-owned Incompletes. A determinate remediation evaluation atomically calls
  Enrollment's idempotent Public command with the obligation id and expected effective
  deadline; a stale deadline rolls back the Case/Standing effects for reload, while a valid
  Incomplete leaves the obligation active. Standing/readiness GETs remain side-effect free
  ([ADR-0041](./adr/0041-standing-observations-not-snapshots.md)).
  Passing a different elective may satisfy its curriculum slot but never supersedes the
  original Course's Grade: that Grade remains in CGPA, Warning, and any D/F termination
  evidence until a successful later Attempt in the same Course supersedes it. Warning
  alone does not block graduation readiness when every curriculum requirement is
  satisfied and CGPA is at least 3.00; Good Standing is not an extra graduation condition.
  An actionable Termination Pending Case temporarily blocks readiness until dismissal or
  Trigger Cleared, which immediately restores the normal calculation.
  Graduation Readiness is an advisory derived result only: Phase 5 does not create a
  Graduated lifecycle value, mutate Student state, or invent a formal graduation approval
  workflow and its non-course/administrative checks. Its Public contract returns the
  authoritative boolean plus Remaining CH, nullable CGPA, the separate Approaching
  Graduation result, and a closed typed blocker set (remaining requirements, unavailable
  CGPA, below-minimum CGPA, termination pending); Approaching remains a curriculum-proximity
  result even when one of those blockers exists, and consumers never reconstruct either.
- **Must-retake priority:** an effective below-C+ Preparatory/Core outcome creates a
  Must-Retake Obligation. Eligible Offerings for those obligations take priority over
  unrelated for-credit Courses: all must be selected when they fit the dynamic ceiling;
  when load or timetable constraints prevent that, the required feasible set is chosen
  deterministically: Preparatory obligations by earliest termination/remediation
  checkpoint, origin time, then Course code, followed by Core obligations by oldest failed
  Attempt then Course code,
  greedily including each Course only if an eligible Offering fits the remaining load and
  timetable; a feasible higher-priority Course is never skipped to optimize Course count,
  credit hours, or capacity use. This fixes the required Courses, while the Student may
  select any eligible non-conflicting Offering for each and the Advisor may change the
  Offering without changing the Course. When no obligation is
  offered, other registration remains possible. An unrelated scheduled Course never makes
  an obligation unavailable: it must be removed or switched while modifications remain
  open. After those windows close, no Enrollment is auto-dropped and any unselected
  obligation carries forward. A full eligible Offering still counts as available: the
  Advisor recommends, then the Course's scoped Department Head may finally approve a
  mandatory-reason, audited capacity exception for the required retake only, without
  displacing anyone. That endpoint uses the Department Head Role plus Course-department
  scope—not `registration.approve` and not a new Permission—so it exposes no ordinary
  registration queue. The exceptional state path is `Pending` (“Pending Advisor”) →
  `AwaitingCapacityOverride` → `Approved`/`Rejected`; ordinary requests skip the new state.
  Every full Offering gets its own decision from that Course's scoped Department Head; the
  request waits for all approvals, while any denial rejects the unchanged batch. Only
  unanimous approval atomically mints Enrollments after final revalidation. Audit neither
  satisfies nor clears the rule. Idempotent capacity reconciliation retires an active
  exception requirement when a normal seat becomes available, preserves its history, and
  auto-approves the already-recommended request only when no exception remains and the
  entire batch passes transactional revalidation; if another requested Offering newly
  fills while the request waits, reconciliation adds its own scoped requirement without
  duplicating an existing decision. It does not reserve seats. Enrollment revalidates the
  rule at every registration approval boundary
  ([ADR-0042](./adr/0042-must-retake-courses-have-registration-priority.md)).
- **Exemption/Transfer/Waiver** (owned by Enrollment per ADR-0002) are standalone,
  course-targeted records rather than synthetic Enrollments ([ADR-0030](./adr/0030-credit-decisions-are-not-enrollments.md)):
  an idempotent listener consumes the Public `AdmissionAccepted` exemption-evidence snapshot
  and creates approved automatic Preparatory Exemptions before first registration; a
  per-Student reconciliation command recovers post-commit listener failures
  ([ADR-0032](./adr/0032-public-admission-evidence-event.md)). A manual workflow handles
  missing or corrected evidence under those same rules; transfer (≤9 CH, min B,
  equivalency, with its inclusive calendar-month request window starting at admission
  acceptance) preserves the external grade and has the Department Head record its
  normalized E-JUST Letter Grade for the B-or-higher gate. The lifetime 9-CH cap and
  Transferred CH use target Curriculum Course hours, revalidated transactionally at final
  approval; source hours remain evidence only, and Revocation frees the recognized hours.
  Waiver is restricted to a prior E-JUST Course at level 400+ completed at C+ or higher,
  with Department Head-verified coverage ≥80%; an official E-JUST transcript may prove a
  legacy record absent from PRS, while external study cannot bypass Transfer rules. A new
  Transfer or Waiver request cannot target a Course after an effective failed Attempt.
  Corrected Exemption evidence may still release a Preparatory requirement after a failed
  or successful Attempt because it proves that the requirement did not apply; the Attempt,
  Grade, raw Earned CH, and CGPA effect remain, while the same Course's Earned CH is excluded
  from target-matched Satisfied progress. If a finalized initial or correction
  Grade creates the failure while a Transfer/Waiver request is under review, an idempotent
  handler terminally rejects it with an attributed system transition and notifies the
  Student and current reviewers. None of the decisions affect CGPA.
  Automatic rules target the six semantic Preparatory Requirement Roles on each
  Program's Curriculum, including its departmental Key Skills Entry, rather than hard-coded
  Course ids ([ADR-0031](./adr/0031-preparatory-requirement-roles.md)). Applications capture
  a structured previous E-JUST Program when applicable; the MSc-to-PhD rule requires the
  previous and admitted Programs to belong to the same Department. Manual Exemptions end
  with the Department Head; Transfers and Waivers proceed through Advisor recommendation,
  Department Head verification, and Academic Affairs Council final decision, using the
  fixed Roles rather than introducing Department/Faculty Council Roles
  ([ADR-0033](./adr/0033-credit-workflows-use-fixed-roles.md)). Manual requests begin as
  editable Drafts, lock during review, support return-for-revision with all earlier reviews
  invalidated on resubmission, and retain an append-only transition history; Approved and
  Rejected are terminal. Final approval atomically creates a separate immutable academic
  decision linked to the request; automatic Exemptions create decisions directly, and only
  effective decisions feed the Credit-Hour Summary. Decisions are never edited or deleted:
  the same final authority may append a reasoned Revocation, and any replacement takes a
  new request through full review. Automatic-Exemption reconciliation likewise appends
  changes rather than rewriting history. If a Preparatory Exemption is revoked after the
  ordinary completion checkpoint, that restored requirement becomes a distinct Remediation
  Obligation through the
  Grade-Submission Deadline of the first main Semester in which its Course has an eligible
  Offering and the Student can still add it at Revocation time—the current Semester counts
  only through late-add—before it can trigger termination; while active it shares the
  Must-Retake
  deadline-first Preparatory priority, timetable, and full-capacity workflow without
  implying a prior Attempt, and a valid Incomplete at the checkpoint defers normally. With
  no future eligible Offering it remains `AwaitingOffering` without a termination
  checkpoint: Remaining CH/readiness still change, Student/Advisor/scoped Department Head
  are notified, and reconciliation later assigns the deadline and activates priority.
  Enrollment reconciles immediately after local Offering changes, Calendar's minimal
  Public `SemesterChanged` signal, and Catalog's minimal Public `CourseChanged` signal for
  activation changes. Both events carry only their owner id; Enrollment re-reads the
  authoritative Public DTO, with
  `enrollment:reconcile-remediation {student?}` as idempotent recovery. Enrollment owns this
  durable obligation beside the Revocation and exposes its
  state/checkpoint DTO to AcademicStanding, preserving the one-way dependency
  ([ADR-0043](./adr/0043-enrollment-owns-remediation-obligations.md)). An assigned active
  checkpoint extends when that Semester's Grade-Submission Deadline moves later but is
  never shortened by an earlier edit. Every edit records the official and retained
  effective dates in an append-only transition and renews the
  Student/Advisor/scoped-Department-Head notification; after evaluation, its decision
  deadline is frozen. Before Enrollment, institution-controlled loss of the qualifying
  Course/Offering opportunity reassigns to another eligible same-Semester Offering, then
  the earliest later lawful opportunity, else `AwaitingOffering`; the transition is
  retained and notified. Full capacity and Student-created conflicts do not qualify, while
  an existing Enrollment proves the opportunity was used. If a replacement Preparatory
  Exemption is approved first, the same
  Enrollment transaction closes an active or `AwaitingOffering` obligation as
  `ReleasedByExemption`, distinguishing corrected applicability evidence from Course
  completion. That transaction also terminally rejects any `Pending` or
  `AwaitingCapacityOverride` atomic Registration Request containing the released Course,
  preserves its capacity-review history, and notifies the Student/current reviewers; it
  never silently removes the line, so a newly validated batch must be submitted. Its
  academic-change event clears matching evidence from a non-final Termination Case under
  normal reconciliation, but never reverses final termination. If the request already
  produced an in-progress Enrollment, approval leaves it unchanged: ordinary
  drop/withdrawal/grading applies, its eventual Attempt and Grade remain in history and
  CGPA, and the Credit-Hour Summary exposes raw Earned/Exempted facts while excluding that
  Course's Earned CH from target-matched Satisfied progress before applying the cap.
- **Termination workflow:** the Preparatory non-completion trigger is evaluated after the
  enrollment cohort's second consecutive Semester Grade-Submission Deadline, with a valid
  Incomplete deferred until resolution or expiry ([ADR-0037](./adr/0037-preparatory-termination-checkpoint.md));
  the Grade trigger counts D/F effective Grades in two distinct Courses and clears a Course
  after successful remediation ([ADR-0038](./adr/0038-termination-uses-effective-grades.md));
  flags → notify → assigned Advisor assesses → scoped Department Head recommends or
  dismisses → Academic Affairs Council terminates or dismisses → notify
  ([ADR-0039](./adr/0039-termination-recommendation-stages.md)). Pending Advisor scope and
  queues resolve the live assignment; reassignment transfers authority, while an already
  recorded attributed assessment remains valid and the later-stage Case does not restart.
  Subsequent Advisor notifications target the current Advisor. Each Student has at most one
  non-terminal Case, transactionally aggregating all actionable Preparatory and D/F
  components; concurrent reconciliation cannot create parallel workflows. If all triggers clear
  before the final decision, the Case closes automatically as Trigger Cleared; a later
  trigger creates a new Case. If the fingerprint changes while some trigger remains, the
  old Case atomically closes as `EvidenceChanged` and a fresh Advisor-stage Case opens; old
  reviews remain history, neither fingerprint is suppressed, the normal new-Case
  notification is sent, and unchanged Termination Pending creates no duplicate Standing
  Observation. Department Head or Council dismissal suppresses the exact
  deterministic trigger-evidence fingerprint and lets Standing fall through. Preparatory
  components include requirement id, ordinary/remediation checkpoint identity, and current
  Grade/Withdrawal/decision/Revocation evidence ids—including explicit no-Attempt—so later
  authoritative evidence may open a new Case while unchanged non-completion stays
  suppressed. A final termination atomically pushes `Terminated` onto the
  Identity-owned Student Lifecycle Status while retaining R02 and historical read access;
  Enrollment's idempotent Public closeout command system-rejects every non-terminal
  Registration and Credit Decision Request in the same transaction, retaining evidence,
  reviews, capacity decisions, existing Enrollments, and effective decisions; new academic
  mutations are blocked, and reinstatement is never inferred automatically. Final
  termination and replacement Exemption approval acquire one
  transaction-scoped Identity-owned Student guard before other module locks and revalidate:
  Exemption-first clears the evidence, termination-first blocks approval, and stale outcomes
  cannot both commit
  ([ADR-0040](./adr/0040-termination-pushes-student-lifecycle-status.md)).
- **Inherited from Phase 4.6, not revisited:** every list this phase adds is born on the `Support`
  query contract ([ADR-0025](./adr/0025-support-layer-query-contract.md)) — paginated envelope, an
  actor-aware filterable-iff-readable allowlist, and a `/export` sibling
  ([ADR-0027](./adr/0027-export-sibling-route-audited.md)) — and any derived value it wants to
  filter, sort or chart on arrives as a batch/aggregate method on its own module's `Public` facade
  through the owner-local batch contract ([ADR-0028](./adr/0028-aggregate-facades-sql-php-parity.md)). This
  phase also ships **its own Dashboard Cards** (Academic Standing, Credit-Hour Summary) as part of
  its own work, per ADR-0024 — one row in `frontend/src/app/dashboardCards.tsx`, one component
  inside its own feature, one `can:`-gated read endpoint in its own module.
- **Demo:** finalize a grade → the Student's CH Summary and Standing update automatically.

### Phase 6 — Records & Transcript (SRS §4.9)

**Status: specified, not implemented.** The approved scope is published as
[the Phase 6 specification](../.scratch/phase-6/spec.md), marked `ready-for-agent` in the
local issue tracker.

- **Scope expanded during design:** include Student requests for official Transcripts,
  approval queues, fees, delivery tracking, and manual refunds; these workflows are not
  deferred. The request lifecycle is resolved below and in SRS §4.9.
- **Request approval resolved:** Student Affairs alone approves or rejects an official
  Transcript Request with a reason. Approval authorizes processing; issuance is separate
  and requires the fee requirement to be satisfied. No Advisor, Department Head, or Dean
  approval stage is added.
- **Issuance gate resolved:** every official issuance requires an approved Student request
  and verified payment; no direct-issuance bypass exists. Each request produces exactly
  one issuance. A new issuance reference requires a new request; reopening or printing an
  existing issuance does not create another.
- **Preview consistency resolved:** require Student Affairs to review a preview before
  issuance. Changes to academic or identity content invalidate that preview; refuse stale
  issuance and require review of updated content. The committed snapshot must match the
  reviewed preview in both languages and summaries across all contributing owners.
- **Request limit resolved:** at most one open Transcript Request per Student, covering
  review, payment, issuance, and delivery. Rejection, completed delivery, cancellation
  without a refund, or completed cancellation refund releases the limit. An outstanding
  refund keeps a cancelled request open; concurrent submissions cannot bypass the limit.
- **Request editing resolved:** submission fixes delivery method and fee; changing delivery
  requires cancellation and a new request. Payment evidence changes only through approved
  submission/correction steps. Rejected requests remain terminal, without resubmission.
- **Lifecycle resolved:** Submitted → Awaiting Payment → Payment Under Review ↔ Payment
  Correction Required → Ready for Issuance → Completed (digital) or Ready for Collection
  → Completed. Rejected is terminal. Pre-issuance Cancellation Pending pauses processing;
  denial restores the prior stage, approval enters Refund Pending → Cancelled when a
  verified payment is owed, otherwise Cancelled directly. Keep attributed, timestamped
  transitions and notify the Student of decisions and required actions. Issuance validity
  is separate from request status.
- **Terminated-Student services resolved:** Terminated Students may request historical
  official Transcripts and complete payment/cancellation/refund/delivery. Termination
  preserves pending Transcript Requests and does not run Enrollment's academic-request
  closeout against them. Document services do not alter academic facts or restore rights
  ([ADR-0048](./adr/0048-terminated-students-retain-transcript-services.md)).
- **Payment handling resolved:** the Student submits a payment reference and receipt;
  Student Affairs manually verifies payment and records the amount, verifier, and
  verification timestamp. Evidence upload alone does not satisfy the fee requirement.
  Online payment-gateway integration is explicitly deferred in the localhost build.
- **Request/financial access resolved:** only the owning Student and Student Affairs read
  request processing history, payment references/receipts, and refund evidence, retaining
  the existing administrator override. Advisors' Transcript/issuance access does not grant
  request or financial access; public QR views disclose none of these records.
- **Payment correction resolved:** Student Affairs returns unverifiable evidence with a
  mandatory reason. Request approval remains valid, issuance stays blocked, and the
  Student may submit corrected evidence. Preserve all earlier submissions and verification
  decisions; returning evidence does not reject the request.
- **Fee pricing resolved:** Student Affairs manages one standard fee in EGP. Each
  request snapshots the applicable fee at submission; price changes affect only new
  requests. Payment instructions become available after approval. Fee waivers are outside
  Phase 6.
- **Fee setup resolved:** configure a positive EGP fee and bilingual payment instructions
  before accepting official requests; viewing remains available before setup. Each request
  snapshots fee and instructions at submission; later edits affect only new requests.
  Show the captured instructions to the Student after approval.
- **Payment amount resolved:** verify one EGP payment exactly matching the request's
  captured fee; return mismatched evidence. No installments, combining receipts, or
  overpayment balances in Phase 6. A full refund returns the exact verified fee.
- **Cancellation/refund resolved:** before issuance the Student may request cancellation,
  including after payment verification. Student Affairs approves or rejects with a reason.
  Approval stops issuance and creates a refund obligation for the verified payment;
  Student Affairs records manual refund amount, reference, receipt, and completion time.
  Post-issuance revocation is separate and does not automatically trigger a refund.
- **Cancellation concurrency resolved:** pending cancellation blocks issuance and payment
  verification. Rejection resumes processing; approval permanently stops issuance.
  Serialize request mutations and revalidate: issuance-first refuses cancellation,
  cancellation-submission-first blocks issuance and payment verification.
- **Refund amount/completion resolved:** refund the full verified payment in one manual
  refund, without deductions or partial refunds. Record amount, reference, receipt,
  staff attribution, and completion time; completion is once-only and repeated submissions
  cannot record a second refund.
- **Delivery resolved:** the Student selects digital access or collection from Student
  Affairs at request submission. Digital delivery completes when the issued copy is
  available in PRS and sends an in-app notification. Collection progresses from Ready
  for Collection to Collected; Student Affairs records handover time and staff member.
  Postal delivery and courier integration are outside Phase 6.
- **Issuance semantics resolved:** unofficial Transcripts reflect current academic records;
  each official issuance preserves an immutable snapshot, issuer, and timestamp. Later
  corrections require a new issuance ([ADR-0044](./adr/0044-official-transcripts-preserve-issued-snapshots.md)).
- **Bilingual issuance resolved:** each official issuance supports EN/LTR and AR/RTL
  viewing and printing from the same frozen snapshot. Preserve both language versions
  of names and academic labels at issuance; later identity/Catalog edits cannot change
  old copies. Language switching needs no new request, issuance, or fee.
- **Records module:** transcript composition (Enrollments + Grades + CH Summary),
  official vs unofficial, rendered on-screen (bilingual).
- **Ownership resolved:** Records owns requests, fee configuration, payment verification,
  refund obligations, delivery tracking, and issuances. No general Finance module is
  introduced; consume academic and identity facts through their owners' Public contracts
  ([ADR-0049](./adr/0049-records-owns-transcript-service-workflows.md)).
- **Audit/notification resolved:** audit fee changes and request/payment/cancellation/
  refund/issuance/revocation/collection actions. In-app Student notifications cover
  decisions, corrections, issuance, revocation, refunds, and collection; Student Affairs
  uses queues. No email/SMS and no per-scan public QR notification. Transition history
  remains separate from the Audit Log.
- **Content resolved:** both Transcript types include all approved Enrollments grouped by
  Semester, including unfinished Courses labelled In Progress. Show finalized Grades only,
  all retake Attempts, and applicable AU/W/I/FW indicators. Effective Exemptions, Transfers,
  and Waivers appear separately from Enrollments. Official issuance is permitted during an
  ongoing Semester; it certifies the record at issuance rather than Course completion.
- **Fields resolved:** header includes Student name, University ID, Identification Type
  and National ID/passport number, Faculty, Department, Program, degree level, admission
  term, and lifecycle status. Rows show Course code/title, CH, classification, Grade/status,
  and non-CGPA-counting Attempt indicators; include Semester GPA, CGPA, and full per-category
  CH Summary. Official copies show reference/date/issuer/seal/watermark/QR. National
  ID/passport is explicitly included in the full public QR disclosure. Exclude contact
  details, payment information, review notes, and termination-case evidence.
- **Identity source/recovery resolved:** use Identity's admission-time Student identity.
  Fix the admission handoff to preserve Identification Type explicitly beside the number;
  recover existing types from accepted Applications through Public contracts, never number
  format guesses. Block official issuance with a correction message if required identity
  data cannot be recovered.
- **Credit-decision detail resolved:** show effective decisions only, with target Course,
  recognized/released CH, type, and approval date. Transfers show source institution/Course,
  original Grade, and normalized E-JUST Grade; Waivers show prior E-JUST Course/Grade;
  Exemptions show qualification-based reason. Include these fields in public QR content;
  exclude attachments and reviewer notes.
- **Authenticated access resolved:** Students read their own current Transcript and full
  issuance history (including revoked copies); Advisors read the same for live advisees;
  Student Affairs reads all and may issue/revoke. Other Roles gain no additional access;
  retain the existing administrator override and separate public QR access. Historical
  reads remain available to Terminated Students. Use existing Transcript permissions,
  with revocation governed by official-issuance authority.
- **Required delivery:** bilingual on-screen and browser-printable Transcripts; official
  issuances include seal/watermark and QR verification. Server-generated PDF via Browsershot
  and cryptographic digital signatures are explicitly deferred. Issuer attribution is not
  a digital signature ([ADR-0045](./adr/0045-phase-6-print-and-verification-scope.md)).
- **QR disclosure resolved:** an unguessable per-issuance public link displays the full
  issued Transcript without login, not the latest academic record; possession of the link
  grants access to that issuance ([ADR-0046](./adr/0046-qr-links-disclose-full-issued-transcripts.md)).
- **Verification lifetime resolved:** no automatic QR-link expiry; graduation, termination,
  account changes, and newer issuances do not invalidate the link. Explicit Student Affairs
  revocation switches its response to reference and Revoked status only.
- **Revocation resolved:** Student Affairs may explicitly revoke an issuance with a
  mandatory reason and retained actor/timestamp. Its snapshot remains internal; the public
  QR page shows only reference and Revoked status. New issuance does not automatically
  revoke previous copies ([ADR-0047](./adr/0047-official-transcript-revocation-preserves-history.md)).
- **Demo:** view an unofficial Transcript; submit an official request → Student Affairs
  approves → submit/verify payment → preview/issue → public QR verification → digital
  delivery or collection. Also demonstrate approved pre-issuance cancellation → full
  manual refund, and official revocation → reference/status-only QR response, in EN/AR.

### Phase 7 — Reporting & polish (SRS §4.12)
**Keeps its position after Phases 5 and 6, and is now much smaller than it was.** The original
reasoning held that Phase 7 could not be pulled forward because three of its eight reports —
**Academic Standing, Graduation Readiness and Termination** — read models Phase 5 has not built.
That was true of those three and of **no others**, which is what Phase 4.6 acted on: it built the
query/export platform and **five** of the eight (application pipeline, enrollment summary, course
registration summary, GPA distribution, audit log) plus an attendance report §4.12 never listed.
The earlier finding that Phase 7 was mis-positioned produced Phase 4.5; this one produced Phase 4.6.

There is **no Reporting module** — [ADR-0026](./adr/0026-reports-owned-by-modules.md) records why,
departing from this line's own earlier wording: each Report lives in the module owning its spine
data and the SPA composes one Analytics section. **A Report is not a Card**, and both are now
first-class terms in [CONTEXT.md](../CONTEXT.md) with a mechanical test between them — *if it
needs a parameter, it is a Report* — so this is not re-litigated here.

- **The three remaining reports**, each owned by the Phase 5 module that owns its read model and
  each arriving with the Card, filter contract and export the platform already provides: Academic
  Standing, Graduation Readiness, Termination. Graduation Readiness derives satisfied
  curriculum plus CGPA ≥ 3.00 independently of Standing, so Warning alone does not exclude
  an otherwise ready Student; an actionable Termination Pending Case temporarily excludes
  them until dismissal or Trigger Cleared. The report consumes Phase 5's advisory result;
  it does not perform or imply formal graduation, and renders the owner's typed blockers
  rather than deriving its own explanations.
- i18n polish pass (Arabic coverage, RTL layout audit) and any deferred niceties.
- **Demo:** run the standing and graduation-readiness reports; full EN↔AR toggle across the app.

---

## 4. Definition of done (per phase)

- Domain rules implemented **test-first** in **Pest** (unit + feature).
- `/api/v1` endpoints covered by Pest feature tests.
- **One Playwright smoke** exercising the phase's headline flow.
- **Vitest + RTL** tests for non-trivial frontend components only.
- Gates green: **deptrac** (module boundaries), **PHPStan/Larastan**,
  **eslint-plugin-boundaries**, **Pint**, ESLint/Prettier.
- Headline demo works manually; then your **manual review gate** before the next phase.

---

## 5. Phase 4 GPA/CGPA agenda — **CLOSED**

SRS §4.7.2 was intentionally blank. All seven items were resolved during Phase 4's
`/grill-with-docs` session and are implemented; the resolutions live in the Phase 4 section
above, in [CONTEXT.md](../CONTEXT.md), and in
[ADR-0020](./adr/0020-grading-owns-gpa-read-model.md) /
[ADR-0021](./adr/0021-grading-scale-uniform-bands.md). Kept here as the record of what was
asked and what was decided:

1. **Prep-course CGPA treatment** → **letter-graded and counted.** BR-56's "below C+ →
   retake" and §4.8's termination criterion both need it; **P/FL is not modelled at all**.
2. **Formula** → confirmed: `Σ(points × CH) ÷ Σ(CH)` over CGPA-counting, non-superseded,
   graded Enrollments. Semester GPA is the same sum restricted to one Semester.
3. **Different-elective retake penalty** → confirmed: different Courses never supersede each
   other, so FR-REG-006.4's "all grades count" needed **no code at all**. Supersession applies
   only among Attempts *of the same Course*, and picks the chronologically latest by the
   Semester's `starts_at` — unconditionally, so a worse Retake genuinely lowers the CGPA.
4. **Earn threshold vs CGPA** → confirmed and kept **independent**: every letter from A+ to F
   counts (F at 0.00 included), while *earning* a Course's credit hours is a separate `≥ C+`
   (2.50) test pushed onto the Enrollment as `earned` (ADR-0022). A D counts fully and earns
   nothing.
5. **Incomplete → FW** → Incomplete excluded from CGPA *and* Earned CH until resolved, and
   counted in the Semester it was originally taken once it is; **FW excluded permanently**.
6. **Transfer / Waive / Exempted** → confirmed: their accounting effect counts toward
   requirement completion and never CGPA. **Amended by [ADR-0030](./adr/0030-credit-decisions-are-not-enrollments.md):**
   these are standalone course-targeted records, not Enrollment Course Statuses; their
   workflows stay Phase 5.
7. **Rounding / precision** → accumulate `Σ(points × 100 × CH)` in **integer hundredths** (no
   float drift), divide, then **round half-up to two decimals exactly once** — and that single
   figure is authoritative for display *and* for every threshold test (graduation `≥ 3.0`,
   Phase 5's probation `< 3.0`), so display and decisions can never disagree. With no
   CGPA-counting graded Enrollment the CGPA is **`null`**, not 0.00, and renders as a dash.

---

## 6. Execution flow

```
(this session) /grill-with-docs → ROADMAP.md + ADR-0005
   then, one phase at a time:
      /to-prd     → small PRD for the phase
      /to-issues  → vertical-slice issues
      /tdd        → build red-green-refactor
      manual review gate → next phase
```
