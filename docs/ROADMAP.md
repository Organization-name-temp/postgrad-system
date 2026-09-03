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
- **`CourseStatus` ships complete** (all 10 Appendix-D values + counting matrix encoded as
  a static matrix), but only **Regular, Retake, Audit, W** are reachable in Phase 3; drop =
  hard delete, no trace. FW/Incomplete/CGPA-Improvement arrive with Phase 4,
  Transfer/Waive/Exempted with Phase 5.
- **Registration workflow:** `RegistrationRequest` = a batch cart (min-6 CH is batch-level;
  no server-side Draft), lines referencing Offerings and carrying requested mode
  Regular/Audit (**Retake is always derived**). Advisor **"modify" = line-item trim, never
  add**; request statuses Pending/Approved/Rejected; approval mints Enrollments atomically
  (classification + registration-time total CH snapshotted per line, ADR-0017).
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
  **visible marker, not a gate** — the house style throughout.
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
  overrides; blocking registration on an outstanding must-retake obligation; caching the CGPA.
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

### Phase 5 — Academic Standing + Credit-Hour Summary + Exemption/Transfer/Waiver (SRS §4.8, §4.5, §4.6)
- **Derived read models** (on-demand): per-category Credit-Hour Summary (Appendix D
  counting) and Academic Standing (Good/Warning/Probation/Termination-Pending),
  recomputed on `GradeFinalized`/`WithdrawalRecorded`.
- **Exemption/Transfer/Waiver** (owned by Enrollment per ADR-0002): automatic exemption
  rules on enrollment (+ manual workflow), transfer (≤9 CH, min B, equivalency, 1-month
  window), waiver (level 400+, ≥80% coverage). None affect CGPA.
- **Termination workflow:** flags → notify → Dept. Council recommends → Academic Affairs
  Council decides → notify.
- **Demo:** finalize a grade → the Student's CH Summary and Standing update automatically.

### Phase 6 — Records & Transcript (SRS §4.9)
- **Records module:** transcript composition (Enrollments + Grades + CH Summary),
  official vs unofficial, rendered on-screen (bilingual).
- PDF export via Browsershot (seal/watermark/QR) is **optional** — add only if time
  allows; otherwise on-screen/print.
- **Demo:** view an unofficial transcript; SA issues an official one.

### Phase 7 — Reporting & polish (SRS §4.12)
**Keeps its position after Phases 5 and 6.** Three of its eight reports — **Academic Standing,
Graduation Readiness and Termination** — read models **Phase 5 has not built**, so it cannot be
pulled forward; that finding is what produced Phase 4.5 (the surface actually wanted was SRS
§2.3's dashboards, which no phase owned). A **Report is not a Card**: a Card is fixed, scoped to
the acting user and summary, while a Report is filterable, parameterised and cross-student —
Phase 4.5's Catalog Summary Card and this phase's enrollment summary report are different
objects, and the distinction is recorded in [CONTEXT.md](../CONTEXT.md) so it is not
re-litigated here.

- **Reporting module:** the 8 read-only cross-module reports (enrollment summary,
  application pipeline, GPA distribution, standing, graduation readiness, termination,
  audit log, course registration).
- i18n polish pass (Arabic coverage, RTL layout audit) and any deferred niceties.
- **Demo:** run reports; full EN↔AR toggle across the app.

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
6. **Transfer / Waive / Exempted** → confirmed: Required CH yes, CGPA no — already encoded in
   Phase 3's Appendix-D matrix; the workflows themselves stay Phase 5.
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
