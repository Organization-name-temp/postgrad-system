# Handoff — PRS (localhost learning build)

**Project:** Postgraduate Registration System (PRS) for E-JUST.
**Workspace:** `postgrad-system` (git remote `MohamedYasser343/postgrad-system`).
**Last refreshed:** end of **Phase 4 — Grading & GPA/CGPA** (phase 4, ticket 10).

This file is the *entry point for a fresh session*. It carries only what the repo does **not**
already record; everything else is deliberately a pointer, so it cannot drift.

---

## Read these first (don't duplicate them)

- **Plan and per-phase record:** [docs/ROADMAP.md](docs/ROADMAP.md) — §3 has one section per
  phase describing what that phase actually built, plus its Deferred list. §4 is the
  Definition of Done every phase is held to.
- **Requirements source of truth:** [docs/SRS.md](docs/SRS.md) ·
  [docs/business-requirements.md](docs/business-requirements.md)
- **Ubiquitous language:** [CONTEXT.md](CONTEXT.md) — the resolved glossary. Read it before
  naming anything.
- **Load-bearing decisions:** [docs/adr/](docs/adr/) — 0001–0023. Where an ADR contradicts an
  older document it says so explicitly (0020 amends architecture.md §5; 0021 replaces SRS
  Appendix A's grading bands).
- **Structure and seams:** [docs/architecture.md](docs/architecture.md) ·
  [docs/tech-stack.md](docs/tech-stack.md) · [docs/design-system.md](docs/design-system.md)
- **How this repo is built:** [AI_workflow.md](AI_workflow.md) — the
  `/grill-with-docs → /to-prd → /to-issues → /tdd → manual review` loop every phase follows.
- **Issue-tracker convention** (local markdown, not GitHub Issues):
  [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md). Per-phase specs and tickets
  live in `.scratch/phase-N/`.

---

## Where the build is

| Phase | State |
|---|---|
| 0 Foundations · 0.5 Design system · 1 Catalog · 2 Admissions · 2.5 Admissions fixes · 3 Enrollment | **Done, merged to `main`** |
| **4 Grading & GPA/CGPA** | **Done on `phase-4-grading`** — all ten tickets; awaiting the manual review gate before Phase 5 |
| 5 Standing + Credit-Hour Summary + Exemption/Transfer/Waiver | Next |
| 6 Records & Transcript · 7 Reporting & polish | Not started |

**Working convention:** one branch per phase (`phase-N-slug`), merged to `main` by PR after the
phase's headline demo is reviewed by hand. Seven backend modules exist:
`Identity`, `Catalog`, `Admissions`, `Notifications`, `Calendar`, `Enrollment`, `Grading`.

---

## Environment — how to actually run it

Everything below is verified; the details that bite are marked.

```bash
docker compose up -d                          # repo root: Postgres 17 (prs + prs_test)
cd backend && php artisan migrate:fresh --seed --force
composer gate                                 # Pint, PHPStan L6, deptrac, Pest, openapi drift
cd ../frontend && npm run gate                # api:check, ESLint, Prettier, tsc, Vitest, Playwright
```

- **PHP 8.4 and Composer come from Laravel Herd.** ⚠ The `php` on Git Bash's PATH is **8.2**
  and fails the project's `>= 8.4.1` platform check — run artisan/pint/phpstan/pest **from
  PowerShell**, where `php` resolves to Herd's `php.bat`.
- `backend/.env` is gitignored. Recreate with `cp .env.example .env && php artisan key:generate`;
  `.env.example` already carries the real Postgres + Sanctum config.
- **Playwright:** run it from PowerShell too (its `webServer` spawns `php artisan serve`), and
  **seed first** — the seeder-driven smokes are only re-runnable against a fresh seed
  (`php artisan migrate:fresh --seed --force`; stop any leftover `artisan serve` first, or the
  drop blocks on its connection). The suite is **serial by config** and must stay that way: it
  shares one database and one set of seeded accounts whose UI language is server-persisted, so a
  parallel run flips another test's locale mid-flow. See `frontend/playwright.config.ts`.
- `git config core.autocrlf` is `true`, so `frontend/.prettierrc` sets `endOfLine: "auto"`;
  generated API artifacts are pinned LF via the root `.gitattributes`.
- **No `gh` CLI**, which is why the issue tracker is local markdown.

---

## Things that are not obvious from the code

- **Modules are auto-discovered** by `backend/bootstrap/providers.php` (globs
  `modules/*/*ServiceProvider.php`). Every new module must also add its `Public`/`Internal`
  layers **and its allowed edges** to `backend/deptrac.yaml`, or deptrac silently permits
  nothing.
- **Cross-module references are opaque ids with no FK** (ADR-0012) — so there is no relation to
  join through, and a consumer resolves the other side through that module's `Public` facade.
  Everything crossing a seam is a DTO, never an Eloquent model.
- **PHPStan is Larastan level 6** and infers columns from `database/migrations` only, so a column
  added by a *module* migration needs an explicit `@property` on the model.
- **Scribe:** response calls are disabled (spec generation needs no DB) — document responses with
  `@response`. The controller docblock's **first line becomes the operationId**, which becomes the
  Orval hook name, so keep it short. Every model-bound route needs an explicit `@urlParam …
  Example:` or the `openapi.yaml` drift check flakes in CI.
- **The generated client** (`frontend/src/shared/api/generated/`) is committed but excluded from
  ESLint and Prettier; both `composer api:check` and `npm run api:check` are `git diff
  --exit-code` gates, so they only mean something once the generated files are committed.
- **Derived, never stored** is the house rule: period state, attendance percentage, Incomplete
  deadlines, grade supersession and GPA/CGPA are all recomputed on read. Nothing is cached (no
  Redis in this build, ADR-0005), so nothing can drift.
- **Scheduled work is hand-run artisan commands**, and every one of them is *flag-and-notify
  only* except `grading:convert-expired-incompletes`, which the SRS explicitly mandates as a
  state change.

---

## The seeded demo world

`php artisan migrate:fresh --seed --force` builds it; it is deterministic and idempotent, and
every window is a **relative offset from seed time**, so it never goes stale. Shared dev
password for every account: `password`.

- **Role accounts** `r01@ejust.test … r10@ejust.test` (one per role), plus `multi@ejust.test`
  (Advisor + Instructor).
- **Named personas** under `@seed.ejust.test`: applicants in every Admissions status, advisors,
  faculty deans, interviewers, Department Heads, and a `student.*` cohort. The seeders reserve
  that namespace so the Playwright smokes' own data never collides with it.
- **Five Semesters**, chronological: `Fall 2024 → Spring 2025 → Fall 2025` (completed, Phase 4)
  `→ Spring 2026` (running, in its withdrawal window) `→ Fall 2026` (registration open).
  The three completed ones exist because grading needs a *finished* term, and an Incomplete only
  becomes overdue once the **following** term has passed its own week 10 — see
  `GradingSeeder`'s class docblock, which explains the calendar in full.
- **What each phase's demo drives** is the corresponding Playwright smoke: `admissions.spec.ts`,
  `enrollment.spec.ts`, `grading.spec.ts` (the headline test at the bottom of the file), plus
  `headline.spec.ts` for the Phase-0/1 walking skeleton.

---

## Immediate next steps

1. **Run the Phase-4 headline demo by hand** and take it through the per-phase manual review
   gate; then open the PR from `phase-4-grading` to `main`.
2. **Start Phase 5** (SRS §4.8, §4.5, §4.6 — Academic Standing, Credit-Hour Summary,
   Exemption/Transfer/Waiver) with the usual loop: `/to-prd` → `/to-issues` → `/tdd` per ticket
   → `/code-review`. Phase 4 left it two hooks: the `GradeFinalized` domain event, and
   `Grading\Public::GradingRecords` for CGPA/Semester GPA — Phase 5 must consume that contract
   rather than re-deriving the arithmetic (ADR-0020). The three `CourseStatus` values still
   unreachable (`Transfer`, `Waive`, `Exempted`) are exactly Phase 5's remit, at which point the
   Appendix-D counting matrix is fully live.
