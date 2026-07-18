# Spec — Phase 2.5: Admissions Hardening (post-review findings)

**Status:** ready-for-agent
**Feature slug:** phase-2.5
**Phase:** 2.5 — hardening pass on Phase 2 (Admissions), from the manual review gate
**Runtime:** Laravel 12 / PHP 8.4 · React 19 / MUI / TanStack Query (unchanged from Phase 2)
**Sources:** manual review of Phase 2 (2026-07-15) · [Phase 2 spec](../phase-2/spec.md) · new ADRs **[0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)** (validation) / **[0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)** (admin override) / **[0016](../../docs/adr/0016-private-document-serving.md)** (document serving) · [ADR-0008](../../docs/adr/0008-rbac-enums-static-matrix.md) (RBAC) · [CONTEXT.md](../../CONTEXT.md) (new term: **Identification Type**)

---

## Two workstreams

The manual review of Phase 2 produced **bugs** and **enhancements**. They ship separately:

- **Workstream 1 — gate-blocking bugs** land on `phase-2-admissions` **before** the Phase 2 review gate merges, so no known defect is merged. Tracked in [bugfixes.md](./bugfixes.md) (B1–B4). This spec covers them only as context.
- **Workstream 2 — hardening enhancements** (this spec) land on a new `phase-2.5-admissions-hardening` branch off `main` after the Phase 2 gate merges.

## Problem Statement

Phase 2 built the whole admissions pipeline, and manual walk-throughs confirmed it works
end-to-end. But the same walk-throughs exposed that the pipeline is **fragile at the edges**
and **leaks across roles**:

- **The applicant forms accept almost anything.** There is no client-side validation at all
  (plain `useState`, `noValidate`) and the server rules are thin: `national_id` is any
  string ≤ 50 chars, `phone` is any string ≤ 30, `nationality` is free text, GPA is a blanket
  `0–100` untied to its scale, and `english_score` is a blanket `0–300` untied to the test.
  A user learns a field is wrong only as a bulk `422` after submitting. Free-text fields that
  should be closed lists (nationality) invite error and un-searchable inconsistency.
- **Roles see what they shouldn't, and can't see what they should.** Because R09/R10 hold
  every Permission (ADR-0008), the permission-gated nav shows admins every applicant/staff
  item — including "My application", which makes no sense for an account that has no
  Application. Conversely, the reviewer detail screens render only **6** of the ~24 captured
  personal-info fields, so a screener can't actually assess the applicant. And there is no
  way for a Super Admin to reach an Application outside a Dean's Faculty scope.
- **The navigation is a flat, ungrouped list** that will only get longer as later phases add
  modules.

None of this blocks the happy path — it blocks *trust* in the data and *clarity* of who does
what. Phase 2.5 hardens all three.

## Solution

Three cohesive changes, no new modules and no new cross-module edges (deptrac unaffected):

1. **Validation, client + server** ([ADR-0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)).
   Adopt **React Hook Form + Zod** as the SPA form stack; every wizard step validates
   **on blur** against a hand-authored Zod schema, and the backend rules are strengthened to
   match and remain the sole authoritative gate. Push fields toward **closed dropdowns**
   wherever a canonical list exists, to design error out rather than validate it after.

2. **RBAC & data visibility** ([ADR-0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)).
   Split nav/route gating into **persona** items (gated by the actual Role) and **capability**
   items (gated by Permission); give R09/R10 an **administrative override** that unscopes the
   existing reviewer / Dean / interview screens (read *and* write, audited); and render the
   **full** applicant record on reviewer screens.

3. **Navigation grouped by area/module** — Admissions / Catalog / Calendar / Administration,
   each header shown only when the signed-in user has items under it.

## User Stories

**Applicant — validation & guided input**

1. As an Applicant, I want each field validated the moment I leave it, with a clear message
   saying exactly what is wrong, so I can fix mistakes as I go rather than after a bulk submit.
2. As an Applicant, I want to declare whether I hold a **National ID** or a **Passport**, and
   have the number checked against that format (a National ID is exactly 14 digits), so I
   can't submit a malformed identifier.
3. As an Applicant, I want to enter my phone as a **country + number** and have the digit
   count checked for that country, so my contact number is actually reachable.
4. As an Applicant, I want to pick my **nationality from a searchable list** rather than
   typing it, so it's consistent and I don't fat-finger it.
5. As an Applicant, I want to **choose my GPA scale first**, then enter a GPA that is checked
   against that scale's ceiling, so a 90/4.0 is impossible to submit.
6. As an Applicant, I want my **English test score checked against the test I selected** (an
   IELTS of 250 is rejected), so the score is meaningful.

**Reviewer & Dean — seeing the whole applicant**

7. As Student Affairs / a Dean, I want the application detail screen to show the **complete**
   applicant record — identity, contact, address, full academic background, English, program,
   documents, timeline — so I can actually assess it, not a 6-field summary.

**Administrator — oversight**

8. As a System/Super Admin (R09/R10), I want to reach **every** Application across all
   Faculties and Cycles through the existing screens, so I can oversee the whole pipeline.
9. As an admin, I want to **record or override an interview evaluation** when needed (an
   interviewer left, a correction is required), with my action attributed and audit-logged,
   so oversight is possible without impersonation.
10. As an admin, I **don't** want persona-only items like "My application" in my nav, because
    I have no Application — my nav should be administration + oversight, not every role's home
    screen.

**Everyone — navigation**

11. As any User, I want the nav grouped into labelled sections so I can find things quickly,
    and I want to see only the sections relevant to me.

## Implementation Decisions

### Validation ([ADR-0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md))

- **Stack:** React Hook Form + Zod, one schema per wizard step, resolver-wired, `mode:
  'onBlur'` (with `onChange` re-validation once a field has errored). The server keeps inline
  `snapshotRules()` as the authority; both sides are strengthened in lockstep and the server
  rules get the authoritative tests.
- **Identification Type** (new **`id_type`** enum `{NationalId, Passport}` + column on
  `applications`, in the Step-1 snapshot):
  - `NationalId` → `^\d{14}$` (Egyptian 14-digit format).
  - `Passport` → `^[A-Za-z0-9]{6,9}$` (alphanumeric).
  - The existing uniqueness rule (identifier unique within `admission_cycle_id`) is retained.
  - UI: a small ID-type Select drives which format/mask the identifier field uses.
- **Phone:** captured via a country-selector + national-number component backed by
  **libphonenumber-js**; validated to a possible/valid number for the chosen country; stored
  canonical **E.164** (e.g. `+201000000000`). Server re-validates E.164 shape.
- **Nationality:** a searchable MUI **`Autocomplete`** over a bundled **ISO 3166-1** list with
  EN/AR labels; stored as the **alpha-2 code**; server validates the code is in the list.
- **GPA:** the `gpa_scale` Select comes **first**; `graduation_gpa` / `graduation_cgpa` stay
  disabled until a scale is chosen, then each is bounded `0 ≤ v ≤ scaleMax` where
  `scaleMax ∈ {4.0, 4.3, 5.0, 6.0, 7.0, 10.0, 100}`. Cross-field check: client Zod
  `superRefine`, server a custom rule reading `gpa_scale`.
- **English score:** validated against the selected `english_test`'s range —
  IELTS `0–9` (0.5 step), TOEFL iBT `0–120`, TOEFL PBT `310–677`, PTE `10–90`, Duolingo
  `10–160`, Cambridge (C1/C2/B2) Cambridge-Scale `80–230`, `None` → no score (unchanged
  `required_unless` presence rule). The per-test range table is a single shared map,
  consumed by the server rule and mirrored in the Zod schema.
- **Wizard polish** (folds in the deferred half of bug B3/B4): the stepper becomes clickable
  for reachable/completed steps, and the Review step gains per-section **Edit** jumps. Built
  once here, on top of the RHF refactor.

### RBAC & data visibility ([ADR-0015](../../docs/adr/0015-administrative-override-of-scope-guards.md))

- **Persona vs capability gating.** Nav items and route guards are classified:
  - *Persona* items — "My application" (and the `/apply` route), "My interviews" — gate on the
    **actual Role** (`R01` / `R04`), never on an inherited Permission. Admins therefore don't
    see them.
  - *Capability* items — screening, Dean review, decisions, interview scheduling/slots,
    cycles, catalog — stay Permission-gated; admins see and use them.
- **Administrative override.** A single `isAdmin` (holds `R09`/`R10`) bypass is applied
  **consistently at every scope / row-level guard** in Admissions controllers:
  - Dean faculty-scope (`ScopesToDeanFaculty`): an admin is not filtered by assigned Faculty.
  - The reviewer/SA queues return all Applications (all Faculties, Cycles, statuses) for an
    admin.
  - The interviewer row-level check (`InterviewEvaluationController`): an admin may **read and
    write** any Interview's evaluation. An admin-authored score writes an **Audit Log** entry
    (actor = the admin); the assigned interviewer's own scoring stays un-audited as today.
  - Admins reach the evaluation write-path through the (now unscoped) interview screens.
- **Full reviewer record.** `ApplicationReviewPage` and `DeanReviewPage` render the complete
  `ApplicationResource` payload grouped into sections (identity, contact & address, academic
  background, English proficiency, program, documents, timeline) — the API already returns it.

### Navigation

- Nav items gain an area **`section`** (`admissions` | `catalog` | `calendar` |
  `administration`); the shell already renders one `ListSubheader` per non-empty section, so
  this is data + a few new section constants. Personal items (Dashboard, My application) stay
  in the header-less top group.

### Schema & contract

- New migrations on the `phase-2.5` branch (Phase 2 is not amended): add `id_type`
  (enum-backed) to `applications` and its Step-1 snapshot; `nationality` continues as a
  string column now holding an ISO alpha-2 code; `phone` continues as a string now holding
  E.164.
- The new `id_type` field and tightened rules change the API surface, so `openapi.yaml` and
  the Orval client are regenerated and the `api:check` gate re-run. No new routes or module
  edges — **deptrac is unaffected**.

## Testing Decisions

Same seams and philosophy as Phase 2 — assert observable behavior, test-first (Pest / Vitest
/ Playwright).

- **Backend feature tests (primary):** the strengthened `snapshotRules()` — each field's new
  rule as a `422` boundary (14-digit ID vs 13/15; passport length; E.164 phone; nationality
  not in the ISO list; GPA above the chosen scale's ceiling; English score outside the
  selected test's range); the **administrative override** end-to-end (an admin lists/opens an
  out-of-Faculty Application → `200` where a scoped Dean gets `403`; an admin records an
  evaluation for an interview not assigned to them → `200` + an Audit Log row; the assigned
  interviewer path unchanged).
- **Backend unit:** the per-test English-score range map; the GPA-vs-scale rule; the two
  ID-format regexes.
- **Frontend (Vitest + RTL):** the wizard steps' **on-blur** validation and error surfacing;
  the GPA scale-then-value gating; the ID-type-driven format switch; the persona-vs-capability
  nav gating (admin does **not** see "My application"; an Applicant does).
- **Playwright:** extend the headline smoke minimally — an admin signs in and opens an
  out-of-Faculty application (proves the override through the UI); the nav renders grouped
  sections. Keep it lean; the pipeline coverage already exists.

## Out of Scope

- **A dedicated admin data-explorer surface** (a global "all applications" screen and any
  cross-module read-all over users/audit/catalog) — deferred to a future administration phase;
  Phase 2.5 unscopes the *existing* screens only ([ADR-0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)).
- **Real email/SMS delivery, malware scanning, encryption-at-rest, account hardening** — all
  still deferred per Phase 2 / ADR-0005.
- **GPA-scale conversion** — the value is bounded by its scale, not converted between scales.
- **An "acting as" persona switch for admins** — considered and rejected in favour of the
  persona/capability split.

## Further Notes

- **No new modules, no new cross-module edges, no new domain events** — this is a hardening
  pass entirely within existing Admissions / Identity code and the SPA. The one new stored
  concept is **Identification Type** (CONTEXT.md).
- **Depends on the Phase 2 gate having merged** (workstream 1 bugs fixed first). Branch off
  `main` as `phase-2.5-admissions-hardening`, per the project's branch-per-phase pattern.
- **Next step:** `/to-issues` to break this into vertical-slice tickets, then `/tdd`
  red-green-refactor per ticket.
