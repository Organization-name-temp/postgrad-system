# Spec — Phase 0: Walking Skeleton & Foundations

**Status:** ready-for-agent
**Feature slug:** phase-0
**Phase:** 0 of the [Build Roadmap](../../docs/ROADMAP.md)
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [ROADMAP §Phase 0](../../docs/ROADMAP.md) · [architecture.md](../../docs/architecture.md) · [tech-stack.md](../../docs/tech-stack.md) · [CONTEXT.md](../../CONTEXT.md) · ADRs [0001](../../docs/adr/0001-decoupled-spa-rest-api.md)/[0002](../../docs/adr/0002-modular-monolith-seams.md)/[0003](../../docs/adr/0003-postgresql.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md)/[0007](../../docs/adr/0007-hand-rolled-psr4-modules.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)

---

## Problem Statement

The Postgraduate Registration System (PRS) is thoroughly documented — an SRS, an
architecture, a tech stack, a glossary, a phased roadmap, and eight ADRs — but **nothing
runs**. There is no application, no way to log in, no enforced module boundary, no test
harness, and no shared conventions for how the two tiers talk. Before any real domain
work (Admissions, Enrollment, Grading…) can begin, the team needs proof that the whole
stack stands up end-to-end and a foundation the later phases can build on without
re-litigating cross-cutting decisions phase after phase.

From the point of view of the people who touch the system today:

- **A developer** cannot open the project and run it, cannot log in as a given role to
  try a flow, and has no gate that tells them whether a change respects the architecture.
- **Every eventual end user** (Applicant through Super Administrator) has no front door:
  no login, no notion of what they're allowed to do, no bilingual interface.
- **A future maintainer** has no guardrail stopping the "modular monolith" from quietly
  eroding into a big ball of mud the first time someone reaches across a module boundary.

## Solution

Build the **thinnest end-to-end vertical slice that proves the entire stack** and
establishes the load-bearing foundations, and nothing more. A developer can clone the
repo, start Postgres, run the backend and frontend, and log in as any of ten seeded roles
into a guarded dashboard that shows who they are and what they may do, in either English
(LTR) or Arabic (RTL), switched at runtime. Under the hood, the slice stands up:

- a monorepo with a Laravel API and a React SPA that talk over a versioned `/api/v1`
  contract through a **typed client generated from the backend's OpenAPI spec**;
- the **Identity & Access** module — Sanctum cookie-session auth and the full R01–R10
  RBAC (roles + the SRS §3.2 permission matrix) enforced on the API and mirrored in the
  SPA — with an append-only audit log recording auth events;
- the **module seam machinery** (hand-rolled PSR-4 modules with a Public/Internal split,
  enforced by deptrac on the back and `eslint-plugin-boundaries` on the front);
- the **i18n loop** (react-i18next + MUI RTL/LTR, persisted per user, with backend
  `Accept-Language` negotiation); and
- the **quality-gate harness** (Pest, Vitest, one Playwright smoke, static-analysis gates)
  wrapped in one-command scripts and a thin CI workflow.

Every later phase is a new module dropped into this skeleton; Phase 0 is the skeleton.

## User Stories

**Authentication**

1. As any user, I want a single login page that accepts my university email and password, so that I have one front door into the system.
2. As any user, I want a language selector on the login page itself, so that I can read the login screen in Arabic or English before I sign in.
3. As any user, I want an incorrect email or password to be rejected with a clear message, so that I know my attempt failed and why (at the level of detail security allows).
4. As any user, I want to remain signed in across page refreshes and navigation, so that I don't have to re-authenticate on every page.
5. As any user, I want to log out, so that I can end my session on a shared machine.
6. As a user who is not signed in, I want to be redirected to the login page when I open a guarded page, so that I'm never shown content I'm not entitled to.
7. As a signed-in user, I want to be sent to my dashboard automatically if I open the login page again, so that I'm not asked to log in twice.

**Roles & permissions (RBAC)**

8. As any signed-in user, I want to see which roles I currently hold, so that I understand the capacity I'm acting in.
9. As any signed-in user, I want to see the specific actions (permissions) I'm allowed, so that I know what the system will let me do.
10. As a user who holds more than one role, I want my effective permissions to be the union of all my roles, so that I can do anything any of my roles allows.
11. As a Student Affairs staff member (or any role), I want the system to grant me exactly the actions the §3.2 matrix assigns my role and no more, so that I can't accidentally exceed my authority.
12. As a developer, I want every permission checked on the API before an action runs, so that authorization cannot be bypassed by calling the API directly.
13. As a developer, I want the SPA to hide or disable actions the current user lacks permission for, so that the interface matches the user's authority (while the API stays the real gate).
14. As a maintainer, I want the R01–R10 role set and the §3.2 matrix to live in one typed, test-covered place, so that the bylaws matrix can't drift into a half-edited state in the database.

**Internationalisation**

15. As an Arabic-speaking user, I want to switch the entire interface to Arabic with a right-to-left layout at runtime, so that I can use the system in my language without reloading.
16. As a returning user, I want my language choice remembered across logins, so that I don't reset it every session.
17. As any user, I want server-side validation and error messages returned in my chosen language, so that the whole experience — not just static labels — is bilingual.

**The walking skeleton / developer experience**

18. As a developer, I want a monorepo containing the Laravel API and the React SPA, so that both tiers are versioned and set up together.
19. As a developer, I want the backend divided into modules with an enforced Public/Internal boundary, so that modules can only reach each other through published facades.
20. As a maintainer, I want the build to fail when one module reaches into another module's internals, so that the modular-monolith boundary can't silently erode.
21. As a frontend developer, I want a typed API client generated from the backend's OpenAPI spec, so that request/response types never drift from the server.
22. As a developer, I want the OpenAPI spec and generated client committed and drift-checked, so that a stale client can't slip through review and the frontend builds without a live backend.
23. As a developer, I want one command that runs every backend quality gate and one for the frontend, so that I can check my work the same way CI does.
24. As a developer, I want those gates to also run automatically in CI when the repo is pushed, so that regressions are caught without relying on memory.
25. As a developer, I want one seeded user for every role (plus one deliberately multi-role user), so that I can log in as any role immediately and exercise the multi-role path.
26. As a developer, I want to run the SPA and API under a single origin in local dev, so that Sanctum's cookie/CSRF auth works without cross-origin configuration.
27. As a developer, I want Postgres provisioned by a one-command docker-compose, so that my dev database matches the engine the GPA/standing math will later depend on.
28. As a developer, I want a single end-to-end smoke test of the headline flow, so that I have continuous proof the two tiers actually talk.

**Audit**

29. As a security reviewer, I want successful logins, logouts, and failed login attempts written to an append-only audit log, so that there is a tamper-evident trail of authentication activity from day one.
30. As a maintainer, I want audit entries writable only through a single audit writer and never updated or deleted, so that the log's append-only guarantee holds as later modules start writing to it.

## Implementation Decisions

**Runtime & repository**
- Build on **Laravel 12 / PHP 8.4** ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md)).
- Monorepo with `backend/` (Laravel) and `frontend/` (React + TypeScript + Vite + MUI) as siblings of the existing `docs/`.

**Modules & boundaries** ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md), [ADR-0007](../../docs/adr/0007-hand-rolled-psr4-modules.md))
- Hand-rolled PSR-4 modules: a `Modules\` → `backend/modules/` map in `composer.json`, one thin auto-discovered `ModuleServiceProvider` per module. No third-party module framework.
- Each module has a `Public/` (the only thing other modules may import — facade, events, DTOs) and an `Internal/` (models, actions, policies, HTTP, private to the module).
- **deptrac** enforces the seam by namespace regex over `Modules\*\Public` / `Modules\*\Internal`. Only **Identity & Access** is scaffolded in Phase 0; later modules are added to the ruleset as they are born, and the first cross-module boundary test lands in Phase 1.
- Frontend mirrors this with `eslint-plugin-boundaries` over `frontend/src/features/*` (the `auth` feature is the only populated one now).

**Identity & Access — RBAC** ([ADR-0008](../../docs/adr/0008-rbac-enums-static-matrix.md))
- A `Role` enum (R01–R10) and a `Permission` enum (one case per SRS §3.2 function).
- The §3.2 matrix is a **static `Permission → Role[]` map in code**; Laravel `Gate`s are registered from it. R09/R10 (System/Super Admin) are treated as holding all permissions.
- Role **assignment** is the only runtime-editable part: a `users`↔`roles` many-to-many pivot. A user's effective permissions are the union across assigned roles.
- `Role`, `Permission`, and the matrix live in `Identity/Public` because the API policies, the `me` contract, and the SPA all consume them.

**Identity & Access — auth**
- **Laravel Sanctum** in SPA cookie-session mode (HttpOnly/SameSite cookie + CSRF).
- Endpoints (all under `/api/v1` except the CSRF cookie route): `GET /sanctum/csrf-cookie`, `POST /api/v1/login`, `POST /api/v1/logout`, `GET /api/v1/me`, `PATCH /api/v1/me`.
- **Seeder:** one user per role `r01@ejust.test` … `r10@ejust.test`, a shared dev password, plus one deliberately multi-role user (e.g. Advisor + Instructor).

**`GET /api/v1/me` contract** — the SPA's authority source (server stays authoritative; the client mirrors for UX only). Shape:
```json
{
  "data": {
    "id": 1,
    "name": "…",
    "email": "r05@ejust.test",
    "preferred_locale": "ar",
    "roles": [{ "code": "R05", "name": "Student Affairs Staff" }],
    "permissions": ["view_application_status", "issue_official_transcript", "…"]
  }
}
```
SPA route guards key off `permissions`; the dashboard variant is chosen by `roles`.

**Audit log**
- An append-only table behind an `AuditLogger` interface in `Identity/Public`. Columns: `actor_user_id` (nullable — a failed login may have no known user), `action`, `subject_type` (nullable), `subject_id` (nullable), `metadata` (jsonb — e.g. ip, user agent), `created_at` only (no `updated_at`; insert-only, no update/delete path).
- Phase 0 writes `login`, `logout`, and `login_failed`. Writing a `login_failed` row is **not** account lockout — lockout stays deferred (see Out of Scope).

**API conventions** (baked into the OpenAPI spec; all later phases inherit)
- Success via API Resources wrapped as `{ data, meta }` (`meta` carries pagination when relevant).
- Errors use Laravel's native `{ message, errors }` with correct HTTP status codes: 422 validation, 401 unauthenticated, 403 unauthorized, 404 not found, 409 domain conflict.

**OpenAPI → typed client**
- **Scribe** generates OpenAPI 3.0 from the backend; **Orval** generates the frontend client (TanStack Query hooks + Axios + Zod schemas) from it.
- Both `openapi.json` and the generated client are **committed**. The backend gate regenerates `openapi.json` and fails on drift; the frontend gate re-runs Orval against the committed `openapi.json` and fails on drift.

**i18n (full loop)**
- `react-i18next` with per-feature namespaces plus a shared `common`; EN + AR resource files. MUI theme direction flips LTR/RTL at runtime with no reload.
- Language preference persisted to a `users.preferred_locale` column via `PATCH /api/v1/me`; localStorage holds the in-session choice.
- An `Accept-Language` middleware sets the Laravel locale so server validation/error messages localize.

**Dev environment**
- **Vite dev proxy**: the browser talks only to the Vite dev server, which proxies `/api` and `/sanctum/csrf-cookie` to Laravel — same-origin, so Sanctum's stateful cookie/CSRF works with minimal config.
- **docker-compose runs Postgres only** (a dev-database convenience; app/orchestration containerization stays deferred per [ADR-0005](../../docs/adr/0005-localhost-learning-build.md)).

**Quality gates**
- `composer gate` runs Pint, PHPStan/Larastan, deptrac, and Pest. `npm run gate` runs ESLint (incl. `eslint-plugin-boundaries`), Prettier, `tsc`, Vitest, and the Playwright smoke.
- A thin `.github/workflows/ci.yml` calls both on push/PR (near-zero-cost wrapper; runs once the repo is pushed).

**Dashboard**
- One generic authenticated dashboard for all roles: shows the signed-in identity, held roles, and effective permissions; includes one **permission-gated demo widget** (renders only when the user holds a chosen permission, proving the guard on both server and client) and the live EN↔AR / RTL toggle. Tailored per-role dashboards are deferred to later phases.

## Testing Decisions

**What makes a good test here:** assert **external, observable behavior only** — HTTP status codes and JSON bodies, audit rows as observable records, the rendered redirect, the RTL flip — never internal class shapes, service wiring, or private methods. Module internals are deliberately swappable behind the facades (ADR-0002/0007), so tests coupled to them would fossilize the internals and fight the architecture. Tests are written **test-first** (Pest) per the roadmap DoD.

**Confirmed seams** (this phase is greenfield, so all seams are new; each is proposed at the highest point, and this phase establishes the prior art the later phases will copy):

1. **Primary — HTTP API feature tests (Pest) against `/api/v1`.** The highest backend seam; exercises the real stack (routing → Sanctum → middleware → gates → DB). Covers: login / logout / failed-login and the correct audit-log row for each; `GET /api/v1/me` returns the agreed shape; permission enforcement via a representative **allow** and **deny (403)** — not every matrix cell; `PATCH /api/v1/me` persists `preferred_locale`; an `Accept-Language` request returns a **localized** validation error.
2. **Lower — permission-matrix unit test (Pest).** The combinatorial §3.2 truth: for each `Role`, its exact `Permission` set. Tested below HTTP on purpose — asserting every role×permission cell through HTTP would be enormous, and the matrix is pure domain data deserving a direct, authoritative test. The API seam only proves enforcement is *wired*.
3. **Cross-stack — one Playwright smoke** (the phase's headline flow, per DoD): log in as a seeded role → guarded dashboard shows identity/roles/permissions → toggle EN↔AR and observe the RTL flip → log out; plus an unauthenticated hit on a guarded route redirects to login.
4. **Frontend — Vitest + React Testing Library, sparingly** (DoD: non-trivial components only). The one Phase 0 component with real logic is the route/permission guard: given a permission set, render the child vs. redirect. The language switcher and dashboard are thin enough to be left to the Playwright smoke.

**Modules under test:** Identity & Access (matrix unit test + API feature tests). The Playwright smoke and the guard component test cover the frontend `auth` feature and the app shell.

## Out of Scope

Deferred per [ADR-0005](../../docs/adr/0005-localhost-learning-build.md) (localhost learning build) and the roadmap; Phase 0 explicitly does **not** include:

- **Auth hardening:** MFA/TOTP, SSO/LDAP/SAML/OAuth (Socialite), account lockout after failed attempts, CAPTCHA, password-policy enforcement, forgot-password / email reset, session listing / force-terminate, geolocation logging. (A `login_failed` audit row is recorded, but no lockout acts on it.)
- **Every other domain module:** Admissions, Catalog, Enrollment, Grading, Academic Standing, Records, and the supporting Notifications / Calendar / Reporting — these are Phases 1–7.
- **Per-role tailored dashboards** and any real domain screens; Phase 0 ships one generic dashboard.
- **Infrastructure services:** Redis, queues/Horizon, Reverb/websockets, MinIO, ClamAV, Vault/KES, real cron/scheduler — none deployed. docker-compose runs Postgres only.
- **Notifications** of any channel (in-app bell, email, SMS).
- **Production NFRs:** rate limiting, PII column encryption, CSP tuning, Kubernetes/HPA, on-prem hosting, TLS ingress, replica/backups, and all scale/availability targets.
- **Exhaustive matrix verification through HTTP** — the full §3.2 truth is covered by the unit-test seam, not by an API test per cell.

## Further Notes

- **Deferred contradiction:** [architecture.md](../../docs/architecture.md) and [ADR-0002](../../docs/adr/0002-modular-monolith-seams.md) describe the derived read models (Credit-Hour Summary, Academic Standing) as "cached in Redis," while [ADR-0005](../../docs/adr/0005-localhost-learning-build.md) computes them on-demand with no cache. This reads as *production target vs. localhost override* (the same relationship the SRS "Production Release" label has to this build), not stale prose — it is flagged for the Phase 5 domain-modeling drill where the derived models are built, and is irrelevant to Phase 0.
- **Glossary:** Phase 0 formalizes the terms **User**, **Role**, **Permission**, and **Audit Log** — see the "Identity & access" section of [CONTEXT.md](../../CONTEXT.md). Use this vocabulary in code and tests.
- **Next step:** `/to-issues` (or `/to-tickets`) to break this spec into vertical-slice implementation tickets under `.scratch/phase-0/issues/`, then `/tdd` to build red-green-refactor, then the manual review gate before Phase 1.
