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

### Phase 4 — Grading & GPA/CGPA (SRS §4.7)
- **Grading module:** instructor mark entry → auto letter-grade mapping (Appendix A) →
  sign/finalize → Department-Head review → `GradeFinalized`.
- Incomplete handling: I allowed at ≥70% completion; **artisan command** converts
  unresolved I→FW at the deadline; reminder logic recorded as in-app notifications.
- **GPA/CGPA calculation** — the SRS §4.7.2 gap; resolve the agenda in §5 first, then
  implement test-first.
- **Demo:** grades entered, signed, reviewed, finalized; GPA/CGPA displayed.

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

## 5. Phase 4 GPA/CGPA agenda (deferred from alignment)

SRS §4.7.2 is intentionally blank. Resolve these — via a focused `/domain-modeling`
drill at the start of Phase 4 — before implementing, and record the outcomes in
`CONTEXT.md` (+ an ADR on the counting interpretation if it proves contentious):

1. **Prep-course CGPA treatment** — letter-graded and counted (favoured by BR-56's
   "below C+ → retake"), or Pass/Fail and excluded?
2. **Formula** — confirm quality-points weighted average
   `Σ(points × CH) / Σ(CH)` over CGPA-counted courses.
3. **Different-elective retake penalty** — BR-55/Appendix D say *both* the failed
   elective and the replacement count in CGPA. Confirm and model.
4. **Earn threshold vs CGPA** — C+ (2.5) is required to earn a course's CH, but sub-C+
   grades still count in the CGPA denominator. Confirm.
5. **Incomplete → FW** denominator effects (excluded until resolved; FW excluded).
6. **Transfer / Waive / Exempted** — count toward Required CH, excluded from CGPA.
7. **Rounding / precision** — compute full precision, display 2 dp; rounding mode.

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
