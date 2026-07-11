# Handoff — PRS Phase 0 build (localhost learning build)

**Project:** Postgraduate Registration System (PRS) for E-JUST.
**Workspace:** `c:\Users\Mohamed\Desktop\Postgrad System`
**Branch:** `main` (nothing committed this session — user chose "don't commit yet").
**Date of handoff:** 2026-07-10.

You are continuing a `/tdd all tickets` run that is building Phase 0. Read this, then
the referenced artifacts. Do **not** re-plan — the plan is settled and recorded.

---

## Read these first (don't duplicate them)

- Phase plan: [docs/ROADMAP.md](docs/ROADMAP.md) (Phase 0 section)
- Spec / PRD: [.scratch/phase-0/spec.md](.scratch/phase-0/spec.md)
- Tickets (the work unit): [.scratch/phase-0/issues/](.scratch/phase-0/issues/) (`01..07-*.md`)
- Ubiquitous language: [CONTEXT.md](CONTEXT.md) (Identity & access: User, Role, Permission, Audit Log)
- Load-bearing decisions: [docs/adr/](docs/adr/) — esp. **0005** (localhost scope), **0006** (Laravel 12 / PHP 8.4), **0007** (hand-rolled PSR-4 modules), **0008** (RBAC = enums + static matrix)
- Issue-tracker convention (local markdown): [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md)

Paths below are relative to the workspace root.

---

## Environment facts (verified this session)

- PHP **8.4.15**, Composer **2.9.1**, Node **24**, npm **11** — all present. PHP has `pdo_pgsql`.
- **Docker is NOT installed. Postgres is NOT running.** `winget` IS available.
- **`gh` CLI is NOT installed** — that's why the issue tracker is local markdown, not GitHub Issues (the repo does have a GitHub remote `MohamedYasser343/postgrad-system`).
- Postgres is provisioned by [docker-compose.yml](docker-compose.yml) (repo root): Postgres 17, db **prs** (+ **prs_test** via `docker/postgres/init/01-create-test-db.sql`), user **prs**, password **secret** (dev-only, in `backend/.env`).

## To unblock DB-dependent work (the current blocker)
```
winget install Docker.DockerDesktop   # then reboot + launch Docker Desktop
docker compose up -d                  # repo root; wait for healthy
cd backend; php artisan migrate --seed
php artisan test                       # HealthTest + RoleEnum + AuthTest expected green
```

---

## Status by ticket

| Ticket | State |
|---|---|
| 01 Walking skeleton | **Done, verified green** (Pest health test + Playwright smoke) |
| 02 Module seams + gates + CI | **Done, verified** (deptrac catches App→Internal; both gates green; `.github/workflows/ci.yml`) |
| 03 OpenAPI→typed client | **Done, verified** (Scribe spec, Orval client, drift-checks) |
| 04 backend (auth/roles/audit) | **Written + statically green** (Pint, PHPStan L6, deptrac, RoleEnum test, FE typecheck). **`AuthTest` (5 tests) RED pending Postgres.** |
| 04 frontend (login/router/guard) | **Not built** — deliberately deferred until backend tests pass on a real DB |
| 05 RBAC authorization | Not started |
| 06 Bilingual i18n loop | Not started |
| 07 Demo capstone + headline smoke | Not started |

**`composer gate` is RED right now** — expected — because it runs `AuthTest`, which needs Postgres. Static checks and `RoleEnum` pass; run `php artisan test --filter=RoleEnum` to confirm the DB-free slice.

---

## Implementation notes NOT captured elsewhere (important)

- **Modules** auto-discovered in `backend/bootstrap/providers.php` (globs `modules/*/*ServiceProvider.php`). deptrac config: `backend/deptrac.yaml` (per-module Public/Internal layers; add each new module's layers as built).
- **User model was relocated** to `Modules\Identity\Internal\Models\User` (was `app/Models/User.php`, now deleted). `config/auth.php` and `database/factories/UserFactory.php` updated to point at it. This keeps deptrac clean (module code never imports `app/`).
- **Roles** are the `Role` enum (`Modules\Identity\Public\Enums\Role`, R01–R10) + a `user_roles` pivot (no roles table). `User::roles()/assignRole()/hasRole()`.
- **Audit**: write only through `Modules\Identity\Public\Contracts\AuditLogger` (impl `Internal\Audit\EloquentAuditLogger`). Table `audit_log` is append-only (no `updated_at`).
- **Sanctum SPA**: `statefulApi()` in `bootstrap/app.php`; `SANCTUM_STATEFUL_DOMAINS` in `backend/.env` includes `localhost:5173`. In feature tests, stateful requests are simulated with header `Origin: http://localhost`; `/me` uses `Sanctum::actingAs()`. CSRF is auto-skipped in the test env.
- **Scribe**: response calls are **disabled** (so spec generation needs no DB) — document responses with `@response` annotations. The controller docblock's **first line becomes the operationId** → keep it short, or Orval hook names balloon (already fixed: `useLogIn`, `useLogOut`, `useCurrentUser`, `useServiceHealthCheck`).
- **Orval**: mutator is `customInstance` in `frontend/src/shared/api/http.ts` (Axios, no baseURL, `withCredentials`). Generated dir `src/shared/api/generated/` is git-committed but **excluded from ESLint + Prettier**; kept LF via root `.gitattributes`.
- **PHPStan** is level 6 (tech-stack targets "max" — later hardening). Larastan infers model columns from `database/migrations` only, so **columns added by module migrations need a `@property` on the model** (see `preferred_locale` on User).
- **Drift-checks** (`composer api:check`, `npm run api:check`) use `git diff --exit-code`; they are **no-ops until the generated files are committed** (untracked files don't show in `git diff`). They become meaningful in CI / after the first commit.

## Known gaps / watch-outs for next session

- **`backend/.env.example` was NOT updated** with the Postgres + `SANCTUM_STATEFUL_DOMAINS` vars (only `.env` was). The CI backend job does `cp .env.example .env`, so **CI will use SQLite and miss Sanctum config** until `.env.example` is fixed. Update it before relying on CI.
- `AuthTest`'s Sanctum stateful behavior is **unverified** (no DB yet) — may need small tweaks when first run.
- Frontend `eslint-plugin-boundaries` v7 own-feature capture rule was deferred — wire + verify it when the first real feature (`auth`) is created in ticket 04 frontend (see comment in `frontend/eslint.config.js`).
- Leftover `backend/database/database.sqlite` from scaffolding — harmless.

---

## Suggested skills for the next session

- **`/tdd`** — resume the red→green loop. First actionable step once Postgres is up: run `AuthTest`, fix to green, then build ticket-04 frontend (login page + React Router + permission-less auth guard + auth state via `useCurrentUser`), then proceed to tickets 05 → 06 → 07 (05 and 06 can run in parallel; 07 depends on both).
- **`/verify`** — after ticket 07, exercise the headline flow (login as a seeded role → guarded dashboard → EN↔AR/RTL toggle → logout) in the real app.
- **`gh-commit`** (or plain commit) — once the user approves committing; recommend a `phase-0-foundations` branch. This also makes the drift-checks meaningful.

## Immediate next action
Ask the user whether Postgres is up. If yes: `docker compose up -d` (if not already), `php artisan migrate --seed`, run `AuthTest`, fix, continue. If no: either wait, or continue building tickets 05/06 backend logic that is DB-light, but note verification will lag.
