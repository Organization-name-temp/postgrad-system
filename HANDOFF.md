# Handoff — PRS (localhost learning build)

**Project:** Postgraduate Registration System (PRS) for E-JUST.
**Workspace:** `postgrad-system` (git remote `MohamedYasser343/postgrad-system`).
**Last refreshed:** 2026-09-28 release-readiness audit of `main` at `44807f6`. Phases 5–7 are merged. Published Phase 7 head CI is green; merge-commit Playwright was still running when checked. Remaining scope and acceptance live in [docs/release-readiness.md](docs/release-readiness.md).

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
- **Load-bearing decisions:** [docs/adr/](docs/adr/) — accepted decisions through 0050; later ADRs
  amend earlier text. Where an ADR contradicts an older document it says so explicitly (0020 amends architecture.md §5; 0021 replaces SRS
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
| **4.6 Query, Filter, Export & Reports** | **Partially executable** — earlier Done claim included planning merges. Phase 7 implements only prerequisite tickets 01, 02, 03, 04, 05, 06, 08 and 15; other work remains outside its goal |
| **5 Standing + Credit-Hour Summary + Exemption/Transfer/Waiver** | **Complete** — all 17 tickets and final review corrections |
| **6 Records & Transcript** | **Merged to main via PR #10** — all 13 tickets, full gates and consolidated review corrections; publication supersedes the older local-only verification note |
| **7 Reporting & polish** | **Merged to main via PR #11** — implemented tickets and bounded prerequisites; published head CI passed all three jobs. Required human acceptance remains unrecorded; merge-commit CI is tracked in release readiness |

**Phase 4.5 is a composition slice**, inserted between Phases 4 and 5 as Phase 0.5 was inserted
between 0 and 1 — it added no module, migration, Permission, Role or domain rule. Phases 5, 6 and
7 keep their numbers and order; **Phase 7 stays last** for the remaining reporting and
polish scope after the academic and Records read models.

**Working convention:** one branch per phase (`phase-N-slug`), merged to `main` by PR after the
phase's headline demo is reviewed by hand. See [the codebase map](docs/agents/codebase-map.md)
for current module owners and entry points.

---

## Environment — how to actually run it

Use [AGENTS.md](AGENTS.md) for setup and package commands. Phase 6 was verified in this
Linux workspace with PHP 8.4.25 and the repository's PostgreSQL service.
Run `COMPOSER_PROCESS_TIMEOUT=0 composer gate` from `backend/` to accommodate the full
suite, and `npm run gate` from `frontend/` after a fresh migrated/seeded development fixture.
The serial browser suite shares persisted account preferences and mutable seeded data;
do not run it alongside another journey or reseed while it is running. Unit workers
are bounded to two for this shared host. The issue tracker remains local Markdown.

---

## Things that are not obvious from the code

- **Modules are auto-discovered** by `backend/bootstrap/providers.php` (globs
  `modules/*/*ServiceProvider.php`). Every new module must also add its `Public`/`Internal`
  layers **and its allowed edges** to `backend/deptrac.yaml`, or deptrac silently permits
  nothing. The executable ruleset is authoritative; avoid inferring dependencies from
  historical phase descriptions.
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
  deadlines, grade supersession and GPA/CGPA are all recomputed on read. Official issuances
  preserve dated bilingual document snapshots, including derived figures,
  under ADR-0044; those figures are historical content rather than live academic balances.
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
  absence), `records.spec.ts` (Phase 6 service/QR/refund/collection and bilingual A4 print),
  plus `headline.spec.ts` for the Phase-0/1 walking skeleton.

---

## Immediate next steps

The user approved a release-readiness and remaining-requirements audit after Phase 7.
Its results are in [the requirements audit](docs/release-requirements-audit.md) and
[the release checklist](docs/release-readiness.md); additional local issues are under
[.scratch/release-readiness/](.scratch/release-readiness/spec.md). Existing Phase 4.6
issues remain canonical and are linked with current blocking edges rather than duplicated.

Start independent retained implementation work with Phase 4.6 Audit Log reporting,
bulk Course import, or an owner-sized slice of the remaining collection-contract migration.
Resolve the new Role-assignment and requirements-reconciliation issues before inventing
policy. The localhost runbook and current-baseline human acceptance can proceed separately.
CI/merge proves publication and technical checks, not manual Arabic/print acceptance.

Phase 6 merged as `b3a3ae7`; Phase 7 merged as `44807f6`. Published Phase 7 head
`a1d01f9` passed [all three CI jobs](https://github.com/MohamedYasser343/postgrad-system/actions/runs/36382219491).
The [merge-commit run](https://github.com/MohamedYasser343/postgrad-system/actions/runs/36394320641)
had green backend/frontend jobs and in-progress Playwright at the audit check; recheck
its terminal result before selecting a release. Older phase trackers retain chronological
local-only/no-push notes; those are superseded for publication by this record.

Server PDF, cryptographic signing, payment gateways, postal delivery, email/SMS and
production infrastructure/authentication hardening remain explicitly deferred. No
release tag or production deployment has been performed by this audit.
