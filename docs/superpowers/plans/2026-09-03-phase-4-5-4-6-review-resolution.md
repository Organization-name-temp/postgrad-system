# Phase 4.5 and 4.6 Review Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Correct every validated PR #7/#8 review finding, re-review both phases, merge them in order, and remove their branches.

**Architecture:** Phase 4.5 keeps Cards as owner-supplied read projections while moving reusable calendar decisions into Calendar Public APIs, enforcing persona Roles on both server and SPA, and standardizing response/presentation behavior. Phase 4.6 remains documentation-only, replacing cross-module SQL with batched facade computation and correcting report, export, import, and ticket contracts before implementation starts.

**Tech Stack:** PHP 8.4, Laravel 12, Pest, PostgreSQL, TypeScript, React 19, MUI, TanStack Query/Orval, Vitest, Playwright, Markdown ADR/spec/tickets.

**Spec:** `docs/superpowers/specs/2026-09-03-phase-4-5-4-6-review-resolution-design.md`

## Global Constraints

- Backend module internals never read another module's tables; cross-module reads use Public DTO/facade contracts.
- The backend authorizes every request; SPA access gates are UX mirrors only.
- No new Role or Permission is added to the fixed SRS §3.2 matrix.
- Generated `backend/openapi.yaml` and `frontend/src/shared/api/generated/` files are regenerated, never hand-edited.
- Every user-facing string is supplied in English and Arabic, and RTL remains supported.
- UI styling uses `docs/design-system.md` tokens; no literal color, radius, font-size, or spacing values are introduced.
- Behavior changes use red-green-refactor: run the named test and observe the expected failure before editing production code.
- Phase 4.5 must merge before Phase 4.6 is retargeted to `main`.

---

### Task 1: Enforce persona Role gates on Card APIs

**Files:**
- Create: `backend/modules/Identity/Internal/Http/Middleware/EnsureRole.php`
- Modify: `backend/modules/Identity/IdentityServiceProvider.php`
- Modify: `backend/modules/Admissions/Public/Http/routes.php`
- Modify: `backend/modules/Enrollment/Public/Http/routes.php`
- Modify: `backend/modules/Grading/Public/Http/routes.php`
- Test: `backend/tests/Feature/Admissions/AdmissionsCardsTest.php`
- Test: `backend/tests/Feature/Enrollment/EnrollmentCardsTest.php`
- Test: `backend/tests/Feature/Grading/AcademicRecordCardTest.php`
- Test: `backend/tests/Feature/Grading/OutstandingIncompletesCardTest.php`
- Test: `backend/tests/Feature/Grading/GradingCardsTest.php`

**Interfaces:**
- Consumes: `User::hasRole(Role $role): bool` and the fixed `Role` enum.
- Produces: route middleware syntax `role:R01`, `role:R02`, `role:R04`, and `role:R06`.

- [ ] **Step 1: Write failing role-authority tests**

Add an administrator-with-inherited-permission assertion to each persona Card test and add wrong-role assertions for R04/R05/R06. The key assertion shape is:

```php
actingAsRole(Role::SystemAdmin);
getJson('/api/v1/admissions/cards/my-application')->assertForbidden();

actingAsRole(Role::StudentAffairs);
getJson('/api/v1/grading/cards/incompletes')->assertForbidden();

actingAsRole(Role::Instructor);
getJson('/api/v1/grading/cards/sheets-awaiting-review')->assertForbidden();
```

- [ ] **Step 2: Verify RED**

Run:

```bash
cd backend
php artisan test tests/Feature/Admissions/AdmissionsCardsTest.php \
  tests/Feature/Enrollment/EnrollmentCardsTest.php \
  tests/Feature/Grading/AcademicRecordCardTest.php \
  tests/Feature/Grading/OutstandingIncompletesCardTest.php \
  tests/Feature/Grading/GradingCardsTest.php
```

Expected: the inherited-permission and wrong-persona requests return 200 instead of 403.

- [ ] **Step 3: Implement the middleware and apply it independently**

`EnsureRole` accepts variadic role codes, converts them with `Role::tryFrom()`, and aborts 403 unless the authenticated `User` holds at least one. Register it as `role` in `IdentityServiceProvider`. Apply it to My Application (R01), My Registration and Academic Record (R02), Incompletes (R04), and Sheets Awaiting Review (R06), alongside `auth:sanctum` and without relying on inherited `can:` gates.

- [ ] **Step 4: Verify GREEN**

Run the five-file command from Step 2. Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add backend/modules/Identity backend/modules/Admissions/Public/Http/routes.php \
  backend/modules/Enrollment/Public/Http/routes.php backend/modules/Grading/Public/Http/routes.php \
  backend/tests/Feature
git commit -m "fix(dashboard): enforce persona role gates"
```

### Task 2: Move calendar decisions to Calendar and expose actionable Sheet rounds

**Files:**
- Modify: `backend/modules/Calendar/Public/AcademicCalendar.php`
- Modify: `backend/modules/Calendar/Internal/Support/EloquentAcademicCalendar.php`
- Modify: `backend/modules/Calendar/Internal/Support/SemesterCalendar.php`
- Modify: `backend/modules/Calendar/Public/SemesterSummary.php`
- Modify: `backend/modules/Calendar/Internal/Support/SemesterWindowsCardBuilder.php`
- Modify: `backend/modules/Grading/Internal/Support/GradeSheetsCardBuilder.php`
- Test: `backend/tests/Unit/Calendar/SemesterCalendarTest.php`
- Test: `backend/tests/Feature/Calendar/SemesterManagementTest.php`
- Test: `backend/tests/Feature/Calendar/SemesterWindowsCardTest.php`
- Test: `backend/tests/Feature/Grading/GradingCardsTest.php`

**Interfaces:**
- Produces: `AcademicCalendar::currentForAcademicWork(): ?SemesterSummary`.
- Produces: `SemesterSummary::openWindows(CarbonInterface $now): list<string>`.
- Produces: `SemesterSummary::nextDeadline(CarbonInterface $now): ?array{key: string, at: CarbonInterface}`.
- Produces: one actionable `GradeSheetStatus` per Offering, preferring the newest non-finalized follow-up round over the Initial round.

- [ ] **Step 1: Write failing Calendar derivation tests**

Cover overlapping registration/add-drop windows, the next future deadline, no future deadline, and selection of the latest non-future Semester:

```php
expect($summary->openWindows($now))->toBe(['registration', 'add_drop'])
    ->and($summary->nextDeadline($now)['key'])->toBe('registration_closes_at');

expect(app(AcademicCalendar::class)->currentForAcademicWork()?->id)->toBe($latestStarted->id);
```

- [ ] **Step 2: Verify Calendar RED**

Run:

```bash
cd backend
php artisan test tests/Unit/Calendar/SemesterCalendarTest.php \
  tests/Feature/Calendar/SemesterManagementTest.php \
  tests/Feature/Calendar/SemesterWindowsCardTest.php
```

Expected: methods are undefined and the Card builder still owns the deadline table.

- [ ] **Step 3: Implement Calendar-owned derivations**

Move the existing deadline/window mapping unchanged into `SemesterCalendar`, delegate from `SemesterSummary`, and implement `currentForAcademicWork()` as the Semester with maximum `starts_at` where `starts_at <= now()`. Make the Card builder call only these Public methods.

- [ ] **Step 4: Write the failing correction-round Card test**

Create a finalized Initial Sheet and a Draft Correction for the same Offering, then assert:

```php
gcSheetsCard()->assertOk()
    ->assertJsonPath('data.unfinalized_count', 1)
    ->assertJsonPath('data.offerings.0.sheet_status', GradeSheetStatus::Draft->value);
```

- [ ] **Step 5: Verify grading RED**

Run `cd backend && php artisan test tests/Feature/Grading/GradingCardsTest.php`.
Expected: `unfinalized_count` is 0 and status is `Finalized`.

- [ ] **Step 6: Implement actionable-round selection**

Replace `initialSheetStatuses()` with one bulk query for every round on the selected Offering IDs. For each Offering choose the newest round whose status is not Finalized; if none exists choose the Initial round; if no round exists return null. Use `currentForAcademicWork()` rather than selecting a Semester from the acting user's Offerings.

- [ ] **Step 7: Verify GREEN**

Run all four test files listed for this task. Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add backend/modules/Calendar backend/modules/Grading/Internal/Support/GradeSheetsCardBuilder.php \
  backend/tests/Unit/Calendar backend/tests/Feature/Calendar backend/tests/Feature/Grading/GradingCardsTest.php
git commit -m "fix(dashboard): reuse calendar and sheet derivations"
```

### Task 3: Standardize Card resources and justified counts

**Files:**
- Create: `backend/modules/Admissions/Internal/Http/Resources/DashboardCardResource.php`
- Create: `backend/modules/Calendar/Internal/Http/Resources/DashboardCardResource.php`
- Create: `backend/modules/Catalog/Internal/Http/Resources/DashboardCardResource.php`
- Create: `backend/modules/Enrollment/Internal/Http/Resources/DashboardCardResource.php`
- Create: `backend/modules/Grading/Internal/Http/Resources/DashboardCardResource.php`
- Create: `backend/modules/Identity/Internal/Http/Resources/DashboardCardResource.php`
- Modify: `backend/modules/Admissions/Internal/Http/Controllers/DashboardCardController.php`
- Modify: `backend/modules/Calendar/Internal/Http/Controllers/DashboardCardController.php`
- Modify: `backend/modules/Catalog/Internal/Http/Controllers/DashboardCardController.php`
- Modify: `backend/modules/Enrollment/Internal/Http/Controllers/DashboardCardController.php`
- Modify: `backend/modules/Grading/Internal/Http/Controllers/DashboardCardController.php`
- Modify: `backend/modules/Identity/Internal/Http/Controllers/DashboardCardController.php`
- Test: `backend/tests/Feature/Admissions/AdmissionDecisionsCardTest.php`
- Test: `backend/tests/Feature/Admissions/AdmissionsCardsTest.php`
- Test: `backend/tests/Feature/Calendar/SemesterWindowsCardTest.php`
- Test: `backend/tests/Feature/Catalog/CatalogSummaryCardTest.php`
- Test: `backend/tests/Feature/Enrollment/EnrollmentCardsTest.php`
- Test: `backend/tests/Feature/Grading/AcademicRecordCardTest.php`
- Test: `backend/tests/Feature/Grading/GradingCardsTest.php`
- Test: `backend/tests/Feature/Grading/OutstandingIncompletesCardTest.php`
- Test: `backend/tests/Feature/Identity/AdministrationCardTest.php`
- Modify: `.scratch/phase-4.5/spec.md`
- Modify: `docs/adr/0024-permission-composed-dashboard-cards.md`

**Interfaces:**
- Produces: every Card response as `{data: <payload>, meta: <object>}` through a module-local `JsonResource`.
- Preserves: `meta.scope` values `own` and `central` for My Grade Sheets.

- [ ] **Step 1: Write failing envelope assertions**

For every Card endpoint add `->assertJsonStructure(['data', 'meta'])`; assert `meta` is `{}` for ordinary Cards and retain exact `meta.scope` assertions for My Grade Sheets.

- [ ] **Step 2: Verify RED**

Run `cd backend && php artisan test --filter=Card`. Expected: Cards lacking `meta` fail.

- [ ] **Step 3: Implement module-local resources**

Each resource extends `JsonResource`, returns the payload from `toArray()`, and uses `additional(['meta' => $meta])`. Controllers return the resource instead of direct `JsonResponse`. Keep null My Application data representable without generating a second response union.

- [ ] **Step 4: Reconcile count wording with module seams**

Update the Phase 4.5 spec and ADR-0024 to require SQL counts for owner-local scalar sets, while permitting reuse of necessarily materialized rows for business-day calculations, cross-module facade scopes, and bounded previews. Do not change correct facade-scoped implementations merely to add a second query.

- [ ] **Step 5: Verify GREEN and API generation**

Run:

```bash
cd backend
php artisan test --filter=Card
composer api:spec
git add openapi.yaml
composer api:check
```

Expected: Card tests pass and the committed OpenAPI remains synchronized after regeneration.

- [ ] **Step 6: Commit**

```bash
git add backend/modules backend/tests/Feature .scratch/phase-4.5/spec.md \
  docs/adr/0024-permission-composed-dashboard-cards.md backend/openapi.yaml
git commit -m "fix(api): standardize dashboard card envelopes"
```

### Task 4: Correct the shared Card presentation

**Files:**
- Create: `frontend/src/shared/ui/DashboardFigure.tsx`
- Create: `frontend/src/shared/ui/DashboardFigure.test.tsx`
- Modify: `frontend/src/shared/ui/DashboardCard.tsx`
- Modify: `frontend/src/shared/ui/DashboardCard.test.tsx`
- Modify: `frontend/src/features/admin/dashboard/AdministrationCard.tsx`
- Modify: `frontend/src/features/admin/dashboard/SemesterWindowsCard.tsx`
- Modify: `frontend/src/features/admissions/dashboard/AdmissionDecisionsCard.tsx`
- Modify: `frontend/src/features/admissions/dashboard/MyApplicationCard.tsx`
- Modify: `frontend/src/features/admissions/dashboard/ScreeningQueueCard.tsx`
- Modify: `frontend/src/features/catalog/dashboard/CatalogSummaryCard.tsx`
- Modify: `frontend/src/features/enrollment/dashboard/AdviseeRequestsCard.tsx`
- Modify: `frontend/src/features/enrollment/dashboard/MyRegistrationCard.tsx`
- Modify: `frontend/src/features/grading/dashboard/AcademicRecordCard.tsx`
- Modify: `frontend/src/features/grading/dashboard/MyGradeSheetsCard.tsx`
- Modify: `frontend/src/features/grading/dashboard/OutstandingIncompletesCard.tsx`
- Modify: `frontend/src/features/grading/dashboard/SheetsAwaitingReviewCard.tsx`
- Test: `frontend/src/features/admin/dashboard/AdministrationCard.test.tsx`
- Test: `frontend/src/features/admin/dashboard/SemesterWindowsCard.test.tsx`
- Test: `frontend/src/features/admissions/dashboard/AdmissionDecisionsCard.test.tsx`
- Test: `frontend/src/features/admissions/dashboard/MyApplicationCard.test.tsx`
- Test: `frontend/src/features/admissions/dashboard/ScreeningQueueCard.test.tsx`
- Test: `frontend/src/features/catalog/dashboard/CatalogSummaryCard.test.tsx`
- Test: `frontend/src/features/enrollment/dashboard/AdviseeRequestsCard.test.tsx`
- Test: `frontend/src/features/enrollment/dashboard/MyRegistrationCard.test.tsx`
- Test: `frontend/src/features/grading/dashboard/AcademicRecordCard.test.tsx`
- Test: `frontend/src/features/grading/dashboard/MyGradeSheetsCard.test.tsx`
- Test: `frontend/src/features/grading/dashboard/OutstandingIncompletesCard.test.tsx`
- Test: `frontend/src/features/grading/dashboard/SheetsAwaitingReviewCard.test.tsx`
- Modify: `frontend/src/shared/i18n/locales/en.ts`
- Modify: `frontend/src/shared/i18n/locales/ar.ts`

**Interfaces:**
- `DashboardCardProps` gains `onRetry?: () => void`.
- `DashboardFigure` accepts `{label: ReactNode, value: ReactNode}`.
- Each Orval query's `refetch` function is passed as `onRetry={() => void refetch()}`.

- [ ] **Step 1: Write failing shared UI tests**

Add assertions that empty state renders an accessible icon, error state renders a Retry button, clicking Retry invokes the callback once, the title uses `MuiTypography-h5`, and the Paper receives the theme's large radius. Add a `DashboardFigure` semantic rendering test.

- [ ] **Step 2: Verify RED**

Run:

```bash
cd frontend
npm run test -- src/shared/ui/DashboardCard.test.tsx src/shared/ui/DashboardFigure.test.tsx
```

Expected: missing retry/icon/h5 behavior and missing component fail.

- [ ] **Step 3: Implement shared presentation**

Use an MUI empty-state icon with `aria-hidden`, `Button` for Retry, `variant="h5" component="h2"`, `borderRadius: 1.5` (12 px from the theme's 8 px base), and `theme.typography.h4.fontSize` for the skeleton. Extract all repeated local `Figure` helpers into `DashboardFigure`.

- [ ] **Step 4: Add retry translations and wire all query Cards**

Add `common.actions.retry` in English and Arabic. Destructure `refetch` from every Card query hook and pass the callback. Update component tests to assert the hook refetches when Retry is clicked in at least one Card per owning feature; shared tests cover the common behavior.

- [ ] **Step 5: Fix warning contrast with a failing test first**

In `ScreeningQueueCard.test.tsx`, assert the warning copy resolves to the accessible semantic text token. Observe failure, then use a theme-mode callback selecting `warning.dark` on light and `warning.main` on dark without a raw color.

- [ ] **Step 6: Verify GREEN**

Run `cd frontend && npm run test -- src/shared/ui src/features/*/dashboard` followed by `npm run typecheck`. Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add frontend/src/shared/ui frontend/src/shared/i18n frontend/src/features
git commit -m "fix(dashboard): align card states with design system"
```

### Task 5: Align typed SPA gates, destinations, and localization

**Files:**
- Modify: `frontend/src/shared/access/roles.ts`
- Modify: `frontend/src/shared/access/permissions.ts`
- Modify: `frontend/src/app/dashboardCards.tsx`
- Modify: `frontend/src/app/DashboardPage.tsx`
- Modify: `frontend/src/app/DashboardPage.test.tsx`
- Modify: `frontend/src/app/DashboardPage.adminSet.test.tsx`
- Modify: `frontend/src/shared/i18n/locales/en.ts`
- Modify: `frontend/src/shared/i18n/locales/ar.ts`

**Interfaces:**
- Produces: `RoleCode = (typeof ROLE)[keyof typeof ROLE]`.
- Produces: `PermissionCode = (typeof PERMISSION)[keyof typeof PERMISSION]`.
- `DashboardCardEntry.roles` is `readonly RoleCode[]`; `permission` is `PermissionCode`.

- [ ] **Step 1: Write failing composition/localization tests**

Add a table-driven case for R01 through R10 asserting exact Card titles, including R04-only Incompletes and R06-only Review. In Arabic assert role chips use translated role labels and `Fall`/`Spring` enrollment terms render Arabic text rather than the raw enum.

- [ ] **Step 2: Verify RED**

Run `cd frontend && npm run test -- src/app/DashboardPage.test.tsx src/app/DashboardPage.adminSet.test.tsx`.
Expected: incomplete single-role coverage plus English role/term output fail.

- [ ] **Step 3: Implement typed gates and exact Role parity**

Add `advisor`, `dean`, and `council` to `ROLE`; export the union types; type `ADMIN_ROLES` and registry gates. Change Outstanding Incompletes to R04 and Sheets Awaiting Review to R06. Keep My Grade Sheets available to R04/R05 through `grades.enter`.

- [ ] **Step 4: Implement code-based translations**

Add `common.roles.R01` through `R10` and `common.terms.Fall`/`Spring` in both locales. Render `t('roles.' + role.code)` and `t('terms.' + enrollment_term)`; never render backend English labels in localized UI.

- [ ] **Step 5: Verify GREEN**

Run the two tests from Step 2, then `npm run lint && npm run typecheck`. Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/shared/access frontend/src/shared/i18n frontend/src/app
git commit -m "fix(dashboard): align card access and localization"
```

### Task 6: Close Phase 4.5 coverage and terminology gaps

**Files:**
- Modify: `backend/tests/Feature/Identity/RoleAccountSeederTest.php`
- Modify: `frontend/src/app/DashboardPage.test.tsx`
- Modify: `.scratch/phase-4.5/spec.md`
- Modify: `.scratch/phase-4.5/issues/01-dashboard-foundation-academic-record-card.md`
- Modify: `.scratch/phase-4.5/issues/02-seeder-data-for-canonical-role-accounts.md`
- Modify: `.scratch/phase-4.5/issues/03-admissions-tier-1-cards.md`
- Modify: `.scratch/phase-4.5/issues/04-enrollment-tier-1-cards.md`
- Modify: `.scratch/phase-4.5/issues/05-grading-tier-1-cards.md`
- Modify: `.scratch/phase-4.5/issues/06-calendar-catalog-configuration-cards.md`
- Modify: `.scratch/phase-4.5/issues/07-administration-card-and-admin-card-set.md`
- Modify: `.scratch/phase-4.5/issues/08-remaining-tier-2-cards.md`
- Modify: `.scratch/phase-4.5/issues/09-headline-smoke-axe-phase-close-out.md`
- Modify: `CONTEXT.md`
- Modify: `HANDOFF.md`
- Modify: `docs/ROADMAP.md`
- Modify: comments/docblocks in changed dashboard PHP/TypeScript files.

**Interfaces:**
- Preserves canonical `Dashboard` and `Dashboard Card` terminology.
- Produces seeded role-account assertions at the HTTP/Card boundary.

- [ ] **Step 1: Add seeded-world characterization assertions**

After `RoleAccountSeeder`, authenticate representative `r02`, `r03`, `r04`, `r05`, `r06`, `r07`, `r09`, and `multi` users and assert their primary Card payload is non-empty and scoped. Use route assertions, not builder internals.

- [ ] **Step 2: Verify the seeded contract**

Run `cd backend && php artisan test tests/Feature/Identity/RoleAccountSeederTest.php`.
Expected: assertions pass when the existing seeded world already fulfills the contract. A failure identifies a concrete missing fixture; preserve the failing assertion before changing the seeder.

- [ ] **Step 3: Make only required seeder/test corrections**

Adjust `RoleAccountSeeder` only for a role whose required Card lacks representative data. Preserve idempotency and existing Phase 3/4 worlds.

- [ ] **Step 4: Canonicalize prose**

Replace “landing page/surface” with “Dashboard” in Phase 4.5-owned prose and changed source comments. Keep quoted historical text only where explicitly identified as a quote; update `CONTEXT.md`, ROADMAP, HANDOFF, spec, and tickets consistently.

- [ ] **Step 5: Run Phase 4.5 gates**

Run:

```bash
cd backend && composer gate
cd ../frontend && npm run gate
```

Expected: Pint, PHPStan, deptrac, 973+ Pest tests, OpenAPI drift, ESLint, Prettier, TypeScript, 425+ Vitest tests, and Playwright all pass. If local ports 8000/5173 are occupied, rely on the PR CI Playwright job and report the exact local conflict.

- [ ] **Step 6: Regenerate and commit**

```bash
cd backend && composer api:spec
cd ../frontend && npm run api:client
cd ..
git add backend/openapi.yaml backend/database/seeders/RoleAccountSeeder.php \
  backend/tests/Feature/Identity/RoleAccountSeederTest.php backend/modules \
  frontend/src/shared/api/generated frontend/src/app frontend/src/features frontend/src/shared \
  .scratch/phase-4.5 CONTEXT.md HANDOFF.md docs/ROADMAP.md \
  docs/adr/0024-permission-composed-dashboard-cards.md
git commit -m "test(dashboard): close phase 4.5 review gaps"
git push origin phase-4.5-dashboards
```

### Task 7: Rewrite Phase 4.6 aggregate, report, export, and import decisions

**Files:**
- Modify: `docs/adr/0025-support-layer-query-contract.md`
- Modify: `docs/adr/0026-reports-owned-by-modules.md`
- Modify: `docs/adr/0027-export-sibling-route-audited.md`
- Modify: `docs/adr/0028-aggregate-facades-sql-php-parity.md`
- Modify: `.scratch/phase-4.6/spec.md`
- Modify: `CONTEXT.md`
- Modify: `docs/ROADMAP.md`
- Modify: `HANDOFF.md`

**Interfaces:**
- Produces batched Public-facade computation with filtering/sorting before in-memory slicing for cross-module derived values.
- Produces exact report scope tables matching existing ordinary-screen scopes.
- Produces authenticated fetch-to-blob export and per-Course version snapshots for imports.

- [ ] **Step 1: Check out and rebase the stacked branch after PR #7 is clean**

```bash
git checkout phase-4.6-query-filter-export
git rebase phase-4.5-dashboards
```

Expected: the Phase 4.5 correction commits become ancestors of Phase 4.6 without merge commits.

- [ ] **Step 2: Correct ADR-0028 and query-contract prose**

Remove the single cross-module SQL mandate and SQL/PHP duplication. Specify candidate DTO batches, owner-local table queries, existing PHP calculators, derived filtering/sorting before page slicing, SQL pagination for stored columns, and batch-versus-single parity over the seeded cohort.

- [ ] **Step 3: Correct report scopes**

Record exact Application, GPA, Enrollment, Registration, and Attendance scopes from the approved design. Remove Department Head, Student Affairs, Dean, and Council from Attendance Analytics unless an ordinary attendance surface grants them access. Require in-scope/out-of-scope tests for every role.

- [ ] **Step 4: Correct export and import contracts**

Require authenticated Axios fetch-to-blob downloads. Define Export as a List capability and Reports as drill-through consumers. Replace one invalid multi-Course snapshot with one snapshot per changed Course plus one audit event for the atomic import transaction. Cite ADR-0005's explicit malware-scanning deferral while retaining private storage and structural validation.

- [ ] **Step 5: Reconcile glossary, ROADMAP, and HANDOFF**

Apply the same definitions and sequencing in `CONTEXT.md`, ROADMAP, and HANDOFF. Remove claims that contradict the amended ADRs.

- [ ] **Step 6: Commit**

```bash
git add docs/adr/0025-support-layer-query-contract.md docs/adr/0026-reports-owned-by-modules.md \
  docs/adr/0027-export-sibling-route-audited.md docs/adr/0028-aggregate-facades-sql-php-parity.md \
  .scratch/phase-4.6/spec.md CONTEXT.md docs/ROADMAP.md HANDOFF.md
git commit -m "docs(phase-4.6): correct query and report architecture"
```

### Task 8: Make all 17 Phase 4.6 tickets implementable and consistent

**Files:**
- Modify: `.scratch/phase-4.6/issues/01-seeded-cohort-and-assertion-audit.md`
- Modify: `.scratch/phase-4.6/issues/02-query-contract-foundation-screening-queue.md`
- Modify: `.scratch/phase-4.6/issues/03-export-foundation-screening-queue-export.md`
- Modify: `.scratch/phase-4.6/issues/04-aggregate-facades-and-parity-test.md`
- Modify: `.scratch/phase-4.6/issues/05-chart-primitive-and-view-as-table.md`
- Modify: `.scratch/phase-4.6/issues/06-admissions-and-catalog-lists-on-the-contract.md`
- Modify: `.scratch/phase-4.6/issues/07-remaining-lists-on-the-contract.md`
- Modify: `.scratch/phase-4.6/issues/08-analytics-section-application-pipeline-drill-through.md`
- Modify: `.scratch/phase-4.6/issues/09-gpa-distribution-report.md`
- Modify: `.scratch/phase-4.6/issues/10-enrollment-owned-reports.md`
- Modify: `.scratch/phase-4.6/issues/11-audit-log-report.md`
- Modify: `.scratch/phase-4.6/issues/12-richer-dashboard-cards.md`
- Modify: `.scratch/phase-4.6/issues/13-global-search-fan-out.md`
- Modify: `.scratch/phase-4.6/issues/14-column-chooser.md`
- Modify: `.scratch/phase-4.6/issues/15-print-stylesheet-for-reports.md`
- Modify: `.scratch/phase-4.6/issues/16-bulk-course-import.md`
- Modify: `.scratch/phase-4.6/issues/17-headline-smoke-axe-phase-close-out.md`

**Interfaces:**
- Ticket 01 blocks ticket 04.
- Ticket 02 defines comma-separated multi-sort and API documentation requirements.
- Tickets 06–07 enumerate every filterable endpoint and its sibling export or explicit exclusion.
- Ticket 07 requires partial University-ID matching and excludes Academic Standing until Phase 5.

- [ ] **Step 1: Repair the dependency graph**

Make ticket 04 blocked by 01; keep 03 blocked by 02; make 06–07 depend on 02–04 as their consumed contracts require; keep Reports blocked by migrated/exportable lists; make 17 depend on every shipped slice.

- [ ] **Step 2: Restore query and documentation acceptance criteria**

Add exact acceptance bullets for comma-separated sort, partial University-ID matching, Arabic normalization, effective allowlists, unconditional pagination envelopes, every `@queryParam`, preserved model-bound `@urlParam ... Example:`, and regenerated OpenAPI/Orval files.

- [ ] **Step 3: Enumerate export coverage**

For every endpoint migrated in tickets 06–07, name its `/export` sibling. If a picker or personal list is excluded, state the data-minimization reason and test that the route does not exist. Require fetch-to-blob UI behavior.

- [ ] **Step 4: Remove undeclared Phase 5 scope**

Delete Academic Standing filter acceptance from Phase 4.6 and record it under the existing Phase 5 dependency. Keep CGPA-range filtering.

- [ ] **Step 5: Correct report and import tickets**

Copy the exact scope tables into tickets 09–10, use batch/single parity in ticket 04, and require per-Course snapshots plus a single import audit event in ticket 16. State ADR-0005's malware-scan deferral explicitly.

- [ ] **Step 6: Self-review all documents**

Run:

```bash
rg -n 'single SQL|one `catalog_versions`|simply a link|Academic Standing|malware' \
  .scratch/phase-4.6 docs/adr/0025-support-layer-query-contract.md \
  docs/adr/0026-reports-owned-by-modules.md docs/adr/0027-export-sibling-route-audited.md \
  docs/adr/0028-aggregate-facades-sql-php-parity.md docs/ROADMAP.md CONTEXT.md HANDOFF.md
git diff --check
```

Expected: every remaining match is either the amended/rejected alternative or the explicit ADR-0005 deferral, never an active contradictory requirement; `git diff --check` exits 0.

- [ ] **Step 7: Commit and push**

```bash
git add .scratch/phase-4.6
git commit -m "docs(phase-4.6): repair implementation tickets"
git push --force-with-lease origin phase-4.6-query-filter-export
```

The force-with-lease is required only because Task 7 rebases the already-pushed stacked branch; verify the remote SHA still equals the pre-rebase fetched SHA before running it.

### Task 9: Re-review, merge, verify main, and clean up

**Files:**
- Review only: all changed files on PRs #7 and #8.

**Interfaces:**
- PR #7 targets `main`; PR #8 targets Phase 4.5 until #7 merges, then targets `main`.
- Branch deletion occurs only after the corresponding merge succeeds.

- [ ] **Step 1: Run two-axis review on Phase 4.5**

Use `/code-review main` on `phase-4.5-dashboards`. Resolve every hard Standards or high Spec finding and repeat until neither axis reports a blocker.

- [ ] **Step 2: Verify PR #7 CI and merge**

```bash
gh pr checks 7 --watch
gh pr merge 7 --merge --delete-branch
git checkout main
git pull --ff-only origin main
```

Expected: all required checks pass and PR #7 is MERGED.

- [ ] **Step 3: Retarget and review Phase 4.6**

```bash
gh pr edit 8 --base main
```

Use `/code-review main` on `phase-4.6-query-filter-export`. Resolve every hard Standards or high Spec finding and repeat until clean.

- [ ] **Step 4: Verify PR #8 CI and merge**

```bash
gh pr checks 8 --watch
gh pr merge 8 --merge --delete-branch
git checkout main
git pull --ff-only origin main
```

Expected: all required checks pass and PR #8 is MERGED.

- [ ] **Step 5: Run final merged-main gates**

```bash
cd backend && composer gate
cd ../frontend && npm run gate
```

Expected: both full gates exit 0. Use GitHub's Playwright result if unrelated local services still occupy ports 8000/5173.

- [ ] **Step 6: Delete remaining local branches safely**

```bash
cd ..
git branch -d phase-4.5-dashboards
git branch -d phase-4.6-query-filter-export
git fetch --prune origin
git status --short --branch
```

Expected: both branches are absent locally and remotely, and the clean checkout is `main...origin/main`.
