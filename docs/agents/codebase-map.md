# Codebase map

Use this index to find the owning module and the shortest path through the repository. It describes implemented code, not the full future architecture in the SRS.

## Repository shape

| Path | Role |
|---|---|
| `backend/` | Laravel 12 / PHP 8.4 REST API, Pest tests, Scribe OpenAPI generation |
| `frontend/` | React 19 / TypeScript / Vite SPA, Vitest and Playwright tests |
| `docs/` | Requirements, architecture, accepted decisions, roadmap, and design system |
| `CONTEXT.md` | Canonical domain glossary and naming rules |
| `.scratch/phase-*` | Local phase specs and implementation tickets; see `docs/agents/issue-tracker.md` |
| `docker-compose.yml` | PostgreSQL 17 development service only |
| `.github/workflows/ci.yml` | CI entry points and runtime versions |

## Backend

### Entry points and infrastructure

| Path | What to look for |
|---|---|
| `backend/bootstrap/providers.php` | Auto-discovers every `modules/*/*ServiceProvider.php` |
| `backend/routes/api.php` | Intentionally empty of domain routes; modules register their own |
| `backend/modules/*/Public/Http/routes.php` | Versioned API route definitions by module |
| `backend/app/Http/Middleware/SetLocaleFromRequest.php` | `Accept-Language` handling |
| `backend/deptrac.yaml` | Allowed `Public`/`Internal` and cross-module dependency edges |
| `backend/config/*.php` | Application and domain configuration; prefer this over new constants |
| `backend/lang/{en,ar}/` | Server-side localized messages |
| `backend/support/` | Domain-free collection-query parsing and constant-memory XLSX export primitives |
| `backend/database/seeders/` | Deterministic demo world composed by `DatabaseSeeder` |
| `backend/openapi.yaml` | Committed generated API contract |

Each module follows this practical shape:

```text
modules/<Module>/
├── <Module>ServiceProvider.php
├── Public/                 # cross-module facades, contracts, enums, DTOs, routes
└── Internal/               # models, actions/support, HTTP, migrations, listeners
```

### Implemented module ownership

| Module | Owns / handles | Public seam |
|---|---|---|
| `Identity` | users, fixed roles and permissions, student anchors and lifecycle projection, audit log, dean/head assignments, authentication | `Public/Contracts`, `StudentIdentity`, `AdmitStudent`, transaction-scoped `StudentGuard` / `StudentLifecycleWriter`, user/student DTOs |
| `Catalog` | faculties, departments, programs, courses, curricula, catalog version snapshots | course/program/curriculum catalog facades and summary DTOs |
| `Admissions` | admission cycles, applications, documents, review workflow, interviews, decisions | HTTP routes; acceptance hands student creation to Identity through its public contract |
| `Notifications` | persisted in-app notifications and the polled notification API | `Notifier` contract and `NotificationType` |
| `Calendar` | semesters and registration/add-drop/withdrawal/grading windows | `AcademicCalendar` and `SemesterSummary` |
| `Enrollment` | offerings, meetings, registration requests, enrollments, class sessions, attendance, earned-credit state, and automatic/manual Exemption decisions | `EnrollmentRecords`, `ExemptionDecisions`, and enrollment/offering/decision DTOs |
| `Records` | current Transcripts, official requests/issuances, fee/payment/refund evidence, cancellation, collection, revocation, and bearer verification | owner Public contracts and transactional `TranscriptReadGuard` seams consumed by `Internal/Support`; HTTP routes |
| `Grading` | grade sheets, grades, correction rounds, Incompletes, GPA/CGPA read model | `GradingRecords` and `GradeSummary` |

When changing a module, begin at its `Public/Http/routes.php`, follow the route into `Internal/Http/Controllers`, then trace models/support classes and the matching tests. When another module needs data, begin at the owner's existing `Public` facade before considering a new method.

### Backend tests and persistence

- `backend/tests/Feature/<Module>/` covers API and workflow behaviour.
- `backend/tests/Unit/<Module>/` covers pure domain rules; a few cross-cutting unit tests sit directly under `tests/Unit`.
- `backend/tests/Pest.php` and `backend/tests/TestCase.php` hold shared test bootstrap.
- Shared Laravel tables are in `backend/database/migrations`; domain tables are in their owning module's `Internal/Database/Migrations`.
- Factories are centralized in `backend/database/factories` and seeders in `backend/database/seeders`.
- Tests use PostgreSQL database `prs_test`; SQLite is not the supported test substitute.
- Records certification consistency is documented in [ADR-0050](../adr/0050-transcript-consistent-certification.md). `TranscriptCertificationConcurrencyTest` drives competing HTTP operations through independent PostgreSQL sessions and observes real lock waits.
- Larastan reads conventional Laravel migrations more reliably than module migrations. Keep model `@property` annotations synchronized when a module migration adds a column.

## Frontend

### Entry points and layers

| Path | Role |
|---|---|
| `frontend/src/main.tsx` | Browser bootstrap |
| `frontend/src/app/App.tsx` | Router and top-level feature composition |
| `frontend/src/app/AuthenticatedLayout.tsx` | Authenticated shell |
| `frontend/src/app/nav.tsx` | Permission-aware navigation |
| `frontend/src/app/theme/` | Theme construction, fonts, and authoritative colour tokens |
| `frontend/src/features/` | Domain screens and feature-local helpers/tests |
| `frontend/src/shared/access/` | Roles, permissions, and reusable UI access gates |
| `frontend/src/shared/api/http.ts` | Shared Axios instance used by Orval |
| `frontend/src/shared/api/generated/` | Generated TanStack Query hooks and Zod schemas |
| `frontend/src/shared/i18n/` | i18n configuration, direction helpers, EN/AR locale dictionaries |
| `frontend/src/shared/ui/` | Cross-feature UI components |

Feature ownership mirrors user-facing workflows:

- `features/admin`: assignments, admission cycles, offerings, and semesters.
- `features/admissions`: applicant wizard, screening, dean decisions, interviews, and application status.
- `features/auth`: login, auth state, route guard, and dashboard.
- `features/catalog`: faculty/department/program/course management, curricula, tree, and version history.
- `features/enrollment`: registration, advising, on-behalf registration, schedules, attendance, and instructor registers.
- `features/records`: bilingual current/official Transcript documents, service queues/actions/evidence, issuance history, staff fee setup, and public verification outside the authenticated shell.
- `features/grading`: instructor sheets/corrections/Incompletes, department-head review, oversight, and student grades.

Vitest tests are colocated as `*.test.ts` or `*.test.tsx`. Full-stack journeys live in `frontend/e2e/*.spec.ts`; `playwright.config.ts` starts Laravel and Vite, but it expects PostgreSQL to be running and seeded.

## Contract change path

An API change crosses the repository in this order:

```text
module route/controller/resource + feature test
  → Scribe annotations / documented response
  → backend/openapi.yaml
  → Orval-generated endpoints.ts and zod.ts
  → frontend feature consumption + Vitest/Playwright coverage
```

Run `composer api:spec` in `backend/`, then `npm run api:client` in `frontend/`. Scribe response calls are disabled, so controllers need explicit documented responses. Keep controller docblock summary lines short because they become generated operation IDs, and give model-bound route parameters explicit examples.

## Documentation routing

| Change or question | Read/update |
|---|---|
| Domain term or distinction | `CONTEXT.md` |
| Required behaviour or permission | `docs/SRS.md`, then `docs/business-requirements.md` |
| Boundary, ownership, or durable technical decision | Relevant file in `docs/adr/`, then `docs/architecture.md` if its overview changes |
| Runtime/library choice | `docs/tech-stack.md` and the executable manifest/config |
| UI tokens, component language, accessibility, RTL | `docs/design-system.md` |
| Phase scope, deferrals, definition of done | `docs/ROADMAP.md` and matching `.scratch/phase-*` files |
| Resume-session operational notes | `HANDOFF.md`, verified against current Git state |
| Local issue workflow | `docs/agents/issue-tracker.md` |

The repository intentionally implements a localhost learning scope. `docs/architecture.md` and `docs/tech-stack.md` also describe production-oriented components that ADR-0005 defers. Check manifests, Compose, and the code before assuming Redis, queues, websockets, object storage, external identity, or deployment infrastructure exists.

## Student Identification Type recovery

Run `php artisan identity:recover-identification` from `backend/` after migrations to
recover unresolved types from matching accepted Applications through Admissions Public.
The command reports recovered and unresolved counts, preserves resolved admission identity,
and does not infer type from identifier format. Missing evidence blocks official readiness.
