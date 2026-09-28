# Security remediation implementation plan

> **For agentic workers:** Use superpowers:executing-plans to execute these tasks in the current session. The user authorized direct implementation on main.

**Goal:** Close AUDIT-01 through AUDIT-07 and preserve the accepted local learning scope.

**Architecture:** Admissions owns a shared Application read/review scope policy and serialized status transitions. Identity owns session preconditions and failed-login budgets; dependency updates close the generated-code injection.

**Tech stack:** Laravel 12/PHP 8.4, PostgreSQL, React/TypeScript, Orval.

**Spec:** `docs/security-audit/REPORT.md` and the user's instruction to fix its findings directly on main.

## Global constraints

- Preserve unrelated edits; work directly on `main` without resetting or seeding `prs`.
- Cross-module dependencies use Public APIs; regenerate committed OpenAPI/client artifacts.
- Add EN/AR error messages together; preserve admin overrides and multi-role permission unions.
- Email delivery, scanning and production infrastructure remain ADR-0005 deferrals.

## Review focus

- Missing Faculty assignments and missing Program references deny Dean access.
- Student Affairs plus Dean retains university-wide screening access; admin overrides remain valid.
- Stale decisions create neither transitions nor notifications; competing transactions retain one winner.
- Changing account case or IP cannot evade the account budget; rotating accounts cannot evade the IP budget.
- Unsupported login/registration requests fail before persisted or authenticated state changes.

## Task 1: Application scope (AUDIT-01/02)

Files: create `backend/modules/Admissions/Internal/Support/ApplicationAccess.php` and `backend/tests/Feature/Admissions/ApplicationAccessTest.php`; modify screening/document controllers and ApplicationCollection.

Interfaces: `ApplicationAccess::reads(int, Application): bool`, `reviews(int, Application): bool`, `reviewerFacultyIds(int): ?array`; null Faculty scope means Student Affairs/admin university-wide access.

- [x] Write failing HTTP tests for scoped detail/timeline/document reads and approve/return/reject, missing scope, and positive owner/SA/admin/multi-role cases.
- [x] Run `php artisan test --filter=ApplicationAccessTest` and verify the negative cases fail against the audited behavior.
- [x] Apply the shared policy to reads and screening writes; retain dedicated Dean-stage routing rules.
- [x] Rerun the access tests and nearest Admissions tests.

## Task 2: Authentication (AUDIT-03/04)

Files: Identity LoginController, Public RequireSpaSession middleware, provider/routes, config/auth.php, EN/AR auth messages, Admissions routes/RegistrationController, Identity AuthTest and Admissions RegistrationTest.

Interfaces: named `spa-session` middleware rejects missing session with 400; failed-login limits default to 5 per normalized account and 60 per IP in a 60-second window, with localized 429 and Retry-After. Successful logins reset only the account failure budget.

- [x] Write failing tests for blocked sixth attempt, budget expiry, account/IP variation, successful login recovery, and stateless requests with no side effects.
- [x] Run those tests before implementing the middleware and limits.
- [x] Implement and document 400/429 contract responses; exercise EN/AR.
- [x] Rerun authentication/registration tests and regenerate OpenAPI/client artifacts.

## Task 3: Admissions transitions (AUDIT-05)

Files: Application model; create Admissions screening concurrency/regression tests.

Interfaces: existing named transition methods retain signatures; transitionTo locks the persisted row, verifies the bound status still matches, and commits state/history atomically. A stale loser receives 409.

- [x] Write failing stale-handler and competing PostgreSQL-session tests, asserting final status, history and notifications.
- [x] Serialize the transition primitive and atomic two-step screening approval.
- [x] Run narrow tests and all Admissions regressions.

## Task 4: Dependency and proof maintenance (AUDIT-06)

Files: frontend package manifests/lock, backend lock, regenerated frontend client, Orval PoC, audit report/evidence, relevant ADR amendment.

- [x] Preserve original exploit evidence and demonstrate the current marker execution.
- [x] Update Orval and advisory-affected dependencies within compatible majors; repeat advisory scans.
- [x] Convert the Orval reproduction to assert the inert marker does not execute after the update; preserve the historical baseline evidence.
- [x] Replace the vulnerable-behavior PHP PoCs with normal regressions or retest expectations.
- [x] Run composer gate and all frontend gate components, including serial Playwright, and dependency audits; independently review the diff, fix findings, and commit only task files on main. Validation details and timing failures/retests are recorded in docs/security-audit/REMEDIATION.md.

## Review amendment

The specification review found a check/increment race in the initial login budget implementation. Account and IP cache locks now cover credential checks, counters and audit outcomes; contention returns localized 429 with Retry-After 1. Both budget-contention HTTP regressions failed against the unlocked implementation and passed with locks. Cache lock support is checked explicitly through LockProvider. Standards review prompted moving concurrency-test connection/transaction setup inside its cleanup guard. Final verification passed: backend 1,364 tests/8,621 assertions; frontend 684 unit tests, lint/format/types/API drift; serial browser run 176 passes plus four failed cases passing unchanged targeted retests; final Admissions journey passed separately. Advisory scans are clean. Task files are committed on main; unrelated edits are preserved.

## Additional stage authorization finding

AUDIT-07: the shared rejection transition allowed the general Student Affairs screening endpoint to reject four later review stages, contradicting its existing stage contract. A guarded rollback-only HTTP-kernel probe reproduced 200/terminal rejection in all four stages before the fix and 409/no writes afterward. The endpoint now checks its locked current status before every action. EN/AR Feature tests cover all four stages (8 cases/40 assertions). The fresh full backend gate passed against an isolated temporary PostgreSQL cluster; the cluster was removed afterward. Contract regeneration/drift checks passed without further frontend changes, and the complete Admissions browser flow passed on final code against reseeded native prs_test.
