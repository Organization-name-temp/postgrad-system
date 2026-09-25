# Handoff — PRS (localhost learning build)

**Project:** Postgraduate Registration System (PRS) for E-JUST.
**Workspace:** `postgrad-system` (git remote `MohamedYasser343/postgrad-system`).
**Last refreshed:** end of **Phase 5 — Academic Standing and Credit Decisions**.

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
- **Load-bearing decisions:** [docs/adr/](docs/adr/) — 0001–0024. Where an ADR contradicts an
  older document it says so explicitly (0020 amends architecture.md §5; 0021 replaces SRS
  Appendix A's grading bands; 0024 departs from SRS §2.3 and architecture.md on "each role sees
  a tailored dashboard").
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
| 0 Foundations · 0.5 Design system · 1 Catalog · 2 Admissions · 2.5 Admissions fixes · 3 Enrollment · 4 Grading & GPA/CGPA | **Done, merged to `main`** (Phase 4 via PR #6) |
| **4.5 Dashboards** | **Merged** — all nine tickets plus final two-axis review corrections |
| **4.6 Query, Filter, Export & Reports** | **Done** |
| **5 Standing + Credit-Hour Summary + Exemption/Transfer/Waiver** | **Complete** — all 17 tickets and final review corrections |
| 6 Records & Transcript · 7 Reporting & polish | Not started (7 is now much smaller — 4.6 takes five of its eight reports) |

**Phase 4.5 is a composition slice**, inserted between Phases 4 and 5 as Phase 0.5 was inserted
between 0 and 1 — it added no module, migration, Permission, Role or domain rule. Phases 5, 6 and
7 keep their numbers and order; **Phase 7 stays last** because three of its eight reports depend
on read models Phase 5 has not built.

**Working convention:** one branch per phase (`phase-N-slug`), merged to `main` by PR after the
phase's headline demo is reviewed by hand. Eight backend modules exist:
`Identity`, `Catalog`, `Admissions`, `Notifications`, `Calendar`, `Enrollment`, `Grading`,
`AcademicStanding`.

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
  nothing. ⚠ **Known drift, recorded not fixed** (found in Phase 4.5, which did not need it):
  deptrac's `App` layer lists five module `Public` layers but neither `Admissions` nor
  `Notifications`, though the ROADMAP records Phase 2 as having added those edges. Worth fixing
  the next time something in `app/` needs to reach either.
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
  (Advisor + Instructor). They began as pure authorization fixtures; since Phase 4.5 the three
  that owned no data carry a real footprint — `r02@` is a Student with two graded Semesters (so a
  CGPA exists), `r03@` holds advisees with a pending request, and **`multi@` holds both advisees
  and Course Offerings**, which is what makes the multi-role Dashboard demonstrable.
- **Named personas** under `@seed.ejust.test`: applicants in every Admissions status, advisors,
  faculty deans, interviewers, Department Heads, and a `student.*` cohort. The seeders reserve
  that namespace so the Playwright smokes' own data never collides with it.
- **Five Semesters**, chronological: `Fall 2024 → Spring 2025 → Fall 2025` (completed, Phase 4)
  `→ Spring 2026` (running, in its withdrawal window) `→ Fall 2026` (registration open).
  The three completed ones exist because grading needs a *finished* term, and an Incomplete only
  becomes overdue once the **following** term has passed its own week 10 — see
  `GradingSeeder`'s class docblock, which explains the calendar in full.
- **What each phase's demo drives** is the corresponding Playwright smoke: `admissions.spec.ts`,
  `enrollment.spec.ts`, `grading.spec.ts` (the headline test at the bottom of the file),
  `dashboard.spec.ts` (Phase 4.5 — seven logins, each asserting its Card set presence *and*
  absence), plus `headline.spec.ts` for the Phase-0/1 walking skeleton.

---

## Immediate next steps

1. **Start Phase 4.6** (Query, Filter, Export & Reports) with the usual loop: `/to-prd` →
   `/to-issues` → `/tdd` per ticket → `/code-review`. It is designed but not built: the ROADMAP §3
   section and [ADR-0025](docs/adr/0025-support-layer-query-contract.md)–
   [0028](docs/adr/0028-aggregate-facades-sql-php-parity.md) are written, ADR-0024 carries a Phase-4.6
   amendment, and CONTEXT.md has the new **Report** and **Export** entries. Its 17 tickets are
   sequenced **before** Phase 5 for one reason: it changes the
   response envelope of every collection endpoint, so Phases 5–7 are born filterable rather than
   retrofitted. Two things to know going in — the envelope change forces an `openapi.yaml` + Orval
   regeneration across every named collection endpoint (both `api:check` gates are `git diff --exit-code`), and
   `backend/support/` is the project's **first shared backend code**, so `deptrac.yaml` gains a
   layer with seven inbound and **zero** outbound edges.
2. **Then Phase 5** (SRS §4.8, §4.5, §4.6 — Academic Standing, Credit-Hour Summary,
   Exemption/Transfer/Waiver). Phase 4 left it two hooks: the `GradeFinalized` domain event, and
   `Grading\Public::GradingRecords` for CGPA/Semester GPA — Phase 5 must consume that contract
   rather than re-deriving the arithmetic (ADR-0020). The three `CourseStatus` values still
   unreachable (`Transfer`, `Waive`, `Exempted`) are exactly Phase 5's remit, at which point the
   Appendix-D counting matrix is fully live. Phase 4.5 left it one more: **Phase 5 ships its own
   Dashboard Cards** (Academic Standing, Credit-Hour Summary) as part of its own work — one row in
   `frontend/src/app/dashboardCards.tsx`, one component inside its own feature, one `can:`-gated
   read endpoint in its own module. The Dashboard grows with the system rather than being
   revisited, and no placeholder Card was left behind for it (ADR-0024).
