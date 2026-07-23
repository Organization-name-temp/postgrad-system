# 11 — Seeder + headline multi-role Playwright smoke (capstone)

**What to build:** The phase capstone (the Phase 1/2 seeder+smoke precedent). A deterministic, idempotent seeder stands up a living Enrollment world — **two out-of-phase Semesters** so every period-gated flow demonstrates on seed day — and one comprehensive multi-role Playwright smoke drives the headline path through the real stack.

**Blocked by:** 01, 02, 03, 04, 05, 06, 07, 08, 09, 10 (all feature tickets).

**Status:** ready-for-agent

- [ ] Deterministic, idempotent seeder (runs after the Catalog + Admissions seeders; windows computed **relative to seed time**, natural keys stay Fall/Spring 2026, so the demo is evergreen):
  - [ ] **Spring 2026** — *running and inside its withdrawal window*: Offerings over the Phase-1 anchor programs (MSc/PhD Computer Engineering + shared prep), taught by seeded R04 instructors; approved Enrollments with Class Sessions + attendance history, **including an Audit enrollment sitting below 75%** (audit-at-risk visible).
  - [ ] **Fall 2026** — *registration window open now*: Offerings ready to register into; a Student with a **Pending** request in an advisor's queue; a Student positioned so a Fall registration of a Spring course derives a **Retake**; a Student with a **W** on a Spring course.
  - [ ] Students minted properly via `Identity\Public::admit()` (not raw rows), reusing / extending the Phase 2 accepted student; seed identities namespaced so they never clash with the smoke's fresh data.
- [ ] One **multi-role Playwright smoke** (real stack): admin opens a Fall Offering → a Student builds and submits a cart (classification groups + live CH total) → the Advisor trims a line and approves → the Student sees the Approved schedule and **withdraws from a running Spring course (W chip appears)** → an Instructor opens the Spring register, cancels a session, and marks attendance until the **audit-at-risk** flag appears. `@axe-core/playwright` on every new screen; per-step screenshot artifacts uploaded by CI.
- [ ] Full `composer gate` (Pest, Pint, PHPStan/Larastan, deptrac 0) and `npm run gate` (ESLint + boundaries, Prettier, tsc, Vitest, the Playwright smoke) green; openapi/Orval drift-clean.
- [ ] **Note:** tests never rely on the seeder — feature/unit tests use factories + `travelTo`; the seeder exists for the demo and the smoke only.
