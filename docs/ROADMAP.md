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
- **Catalog module:** Faculties, Departments, Programs, Courses, Course Categories
  (Preparatory 6×1 / Program Core 2×3 / Research Elective 3×3), with EN+AR names.
- Program/course CRUD + version history; `Lecture+Tutorial+Lab CH = Total` validation.
- **Interactive program tree view** (FR-PCM-002), expandable, searchable; course detail.
- Realistic **E-JUST seeder** built from the FR-PCM-002 example tree.
- **Demo:** browse the University→Faculty→Dept→Program→Course tree; open course details.

### Phase 2 — Admissions (SRS §4.2)
- **Admissions module:** multi-step application (Personal Info → Email Verify → Documents
  → Program Selection), status machine (Draft → … → Accepted/Rejected, FR-ADM-009).
- Email verification: link **shown on screen / logged** (no email sent).
- Document upload to **local disk** (type/size validation; no malware scan).
- Workflow: SA screening → Dean review → interview scheduling → final decision, with
  configurable SLAs surfaced but enforced via a manual artisan command.
- **Acceptance** publishes `AdmissionAccepted` → Identity mints a University ID and
  transitions R01→R02, creates the academic record, and applies automatic exemptions.
- Minimal **Calendar** support (interview slots) built just-in-time here.
- **Demo:** an applicant applies, is screened/approved/interviewed, accepted → Student.

### Phase 3 — Enrollment (SRS §4.4, §4.5, §4.6 partial)
- **Enrollment module:** `RegistrationRequest` → Advisor approval → `Enrollment`
  (Pending/Approved/Rejected). CH limits (min 6 / max 12, config-driven) + approaching-
  graduation and first-semester exceptions.
- Category rules: internal vs external elective (external needs advisor approval),
  PBL (MSc) vs Seminar (PhD) research course; level rules (500/600/700).
- Add/Drop/Late-Add/Withdrawal periods (Calendar) and **Course Status** accounting
  (Regular, Retake, Audit, W, FW, Incomplete, CGPA-Improvement, Transfer, Waive, Exempted).
- Re-registration / retake caps (max 1) and the elective-vs-core retake distinction.
- **Demo:** a Student registers courses, the Advisor approves, statuses are tracked.

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
