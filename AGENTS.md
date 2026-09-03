# AGENTS.md

This repository is the E-JUST Postgraduate Registration System (PRS): a localhost learning build with a Laravel REST API and a React SPA. Treat the code, executable configuration, and accepted ADRs as the current truth; planning documents may describe later phases that are not implemented yet.

## Start here

1. Read [`CONTEXT.md`](CONTEXT.md) before naming domain concepts. Its terms are canonical.
2. Read [`docs/agents/codebase-map.md`](docs/agents/codebase-map.md) when locating ownership, entry points, tests, or generated code.
3. Read the relevant requirements in [`docs/SRS.md`](docs/SRS.md) and [`docs/business-requirements.md`](docs/business-requirements.md) before changing behaviour.
4. Read the ADRs related to the touched module. Later ADRs amend older architecture text; accepted ADRs win.
5. For phase work, consult [`docs/ROADMAP.md`](docs/ROADMAP.md) and the matching `.scratch/phase-*` spec/tickets. Reconcile status claims in [`HANDOFF.md`](HANDOFF.md) with `git log` because the handoff is a point-in-time snapshot.

## Architectural guardrails

- Backend modules live in `backend/modules/<Module>`. A module owns its tables and business rules. Cross-module calls use the provider module's `Public` facade/contracts and DTOs; never import another module's `Internal` namespace or pass Eloquent models across a seam.
- Cross-module references are opaque IDs without database foreign keys. Resolve foreign data through the owning module's `Public` API. Keep allowed dependency edges explicit in `backend/deptrac.yaml`.
- Put module migrations under `backend/modules/<Module>/Internal/Database/Migrations`. Module providers and routes are auto-discovered; do not add a central provider or route registry.
- Frontend domain code lives in `frontend/src/features/<feature>`. Features may use `shared`, while `frontend/src/app` composes features. Avoid feature-to-feature imports; the ESLint boundary rules are authoritative.
- The backend authorizes every operation. Frontend permission gates improve UX but are not a security boundary.
- OpenAPI is the backend/frontend contract. `backend/openapi.yaml` and `frontend/src/shared/api/generated/` are generated and committed. Change controllers/resources/docs first, regenerate, then consume the generated types and hooks; do not hand-edit generated files.
- Derived academic values stay derived unless an ADR explicitly changes that rule. Existing examples include period state, attendance percentage, grade supersession, Incomplete deadlines, and GPA/CGPA.
- Preserve the bilingual contract. Add English and Arabic backend messages together, update both frontend locale files, and verify RTL as well as LTR behaviour.
- Use theme/design tokens for UI colour and styling. Read `docs/design-system.md` for UI work; raw colour literals outside the token source fail repository conventions.

## Working loop

1. Inspect the relevant public route, controller/resource, domain support code, and nearest tests before editing.
2. Make the smallest vertical change that covers domain rule, API, UI, and tests as required. Follow the repository's test-first workflow for behaviour changes.
3. Run the narrowest relevant tests while iterating, then the affected package gate. Run full-stack Playwright only when the user flow or integration seam changed.
4. Review generated and handwritten diffs separately. Preserve unrelated working-tree changes.
5. Update the source-of-truth document when a decision, domain term, API contract, or phase status changes; avoid copying facts that can be read directly from config.

## Commands

Run the PostgreSQL service from the repository root:

```bash
docker compose up -d
```

Backend commands run from `backend/`:

```bash
cp .env.example .env && php artisan key:generate   # first setup only
php artisan migrate:fresh --seed --force
php artisan test --filter=RelevantTest
composer gate
composer api:spec
```

Frontend commands run from `frontend/`:

```bash
npm ci
npm run test -- path/to/file.test.tsx
npm run lint
npm run typecheck
npm run api:client
npm run gate
```

`composer gate` runs Pint, PHPStan/Larastan, deptrac, Pest, and OpenAPI drift checks. `npm run gate` also runs Playwright and therefore needs a migrated, seeded PostgreSQL database. The Playwright suite is deliberately serial because tests share mutable seeded accounts and one database.

## Completion bar

A change is complete when the relevant behaviour is covered at the proper level, module and feature boundaries pass, generated API artifacts are synchronized, EN/AR and RTL implications are handled, and the affected gate is green. Report any gate not run and the concrete reason.

