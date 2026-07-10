# Build Roadmap — Postgraduate Registration System (PRS)

**Egypt-Japan University of Science and Technology (E-JUST)**

| | |
|---|---|
| **Status** | Agreed plan, v1.0 (from a `/grill-with-docs` alignment session) |
| **Scope decision** | [adr/0005-localhost-learning-build.md](./adr/0005-localhost-learning-build.md) |
| **Source of truth** | [SRS.md](./SRS.md) · [business-requirements.md](./business-requirements.md) · [architecture.md](./architecture.md) · [tech-stack.md](./tech-stack.md) |
| **Ubiquitous language** | [../CONTEXT.md](../CONTEXT.md) |

This roadmap turns the SRS/architecture into a **phased build plan**. It records the
scope and sequencing decisions made during alignment, and defines what each phase
delivers. It is a *plan*, not a spec — the SRS remains the requirements source of truth.

---

## 1. Build scope

This is a **localhost learning build**: the goal is to implement the PRS **domain and
workflow logic** faithfully and run it on a developer machine — *not* to operate a
production system. The SRS is labelled "Production Release"; we deliberately depart from
that. The full rationale and the explicit defer/retain lists are in
[ADR-0005](./adr/0005-localhost-learning-build.md).

**Kept:** two-tier React SPA + headless Laravel REST API (ADR-0001), modular-monolith
seams + deptrac (ADR-0002), PostgreSQL (ADR-0003), full RBAC (R01–R10 + permission
matrix), in-process domain events, and **bilingual Arabic/English + RTL from Phase 0**.

**Deferred / stubbed:** K8s/HPA, on-prem hosting, Redis, MinIO, Reverb, ClamAV,
Vault/KES, Horizon, MFA, SSO/LDAP/SAML, account lockout/CAPTCHA/password-policy,
email/SMS, cron scheduler, sync replica, and all scale/HA NFRs. See ADR-0005.

### Localhost footprint

- **DB:** PostgreSQL (dev), so the GPA/standing window-function math stays honest.
- **No** Redis / MinIO / Reverb / ClamAV / Vault / Horizon.
- **Files:** uploaded to local disk (Laravel `local` filesystem), no malware scan.
- **Derived read models** (Credit-Hour Summary, Academic Standing): computed
  **on-demand** from authoritative records — not cached, so they cannot drift.
  (Redis caching is a documented later optimization, not built now.)
- **Scheduled work** (Incomplete→FW conversion, SLA aging, reminders): implemented as
  **artisan console commands run manually**, not real cron/scheduler.
- **Notifications:** in-app table + a **polled** bell. No email, SMS, or websocket.
- **Auth:** Sanctum cookie-session, email+password, seeded users. Hardening deferred.

---

## 2. Cross-cutting decisions

| Area | Decision | Source |
|------|----------|--------|
| Quality bar | Localhost learning build; production NFRs deferred | Q1 → ADR-0005 |
| App shape | Two-tier (React SPA + Laravel REST API), domain-weighted vertical slices | Q2 (ADR-0001) |
| Auth | Minimal Sanctum session + **full RBAC**; hardening deferred | Q3 |
| Events / notifications | In-process events kept; user-facing = in-app table + polled bell | Q4 |
| Derived models | Computed on-demand (no cache) | derived |
| i18n | **Fully bilingual EN+AR + RTL from Phase 0** (`react-i18next` + MUI direction) | Q6 |
| Sequencing | **Student-lifecycle order** | Q5 |
| Testing / DoD | Domain-TDD (Pest) + API feature tests + 1 Playwright smoke/phase + static-analysis gates | Q9 |
| Execution | ROADMAP now; per phase `/to-prd → /to-issues → /tdd → review`; **one phase at a time** | Q10 |

---

## 3. Phases

Each phase is a **vertical slice**: backend domain logic + `/api/v1` endpoints + a
minimal bilingual MUI UI + tests. A phase is **done** when its domain rules are covered
by test-first Pest tests, its headline flow has a Playwright smoke, and
deptrac + PHPStan/Larastan + eslint-boundaries + Pint are green (see §4). Build order is
strict; do not start a phase before the previous one's review gate passes.

### Phase 0 — Walking skeleton & foundations
The thinnest end-to-end slice that proves the whole stack.
- Monorepo: `backend/` (Laravel 11) + `frontend/` (React + TS + Vite + MUI).
- Module scaffold under `backend/modules/<Name>/{Public,Internal}` + **deptrac** rules;
  `eslint-plugin-boundaries` for the frontend feature seams.
- **Identity & Access:** Sanctum SPA cookie auth (login/logout), seeded users, the
  R01–R10 role model + permission matrix (SRS §3.2), API policy middleware + SPA
  route guards. Audit-log table stub (append-only), written by later events.
- **OpenAPI → typed client** pipeline (Scribe → openapi-typescript/Orval + Axios).
- **i18n scaffold:** `react-i18next`, EN+AR resource files, MUI RTL/LTR theme toggle.
- Test harness: Pest, Vitest + RTL, one Playwright smoke; CI-runnable gate scripts.
- **Demo:** log in as any seeded role → land on a guarded, language-switchable dashboard.

### Phase 1 — Catalog (SRS §4.3)
- **Catalog module:** Faculties, Departments, Programs, Courses, Course Categories
  (Preparatory 6×1 / Program Core 2×3 / Research Elective 3×3), with EN+AR names.
- Program/course CRUD + version history; `Lecture+Tutorial+Lab CH = Total` validation.
- **Interactive program tree view** (FR-PCM-002), expandable, searchable; course detail.
- Realistic **E-JUST seeder** built from the FR-PCM-002 example tree.
- **Demo:** browse the University→Faculty→Dept→Program→Course tree; open course details.

### Phase 2 — Admissions (SRS §4.2)
- **Admissions module:** multi-step application (Personal Info → Email Verify → Documents
  → Program Selection), status machine (Draft → … → Accepted/Rejected, FR-ADM-009).
- Email verification: link **shown on screen / logged** (no email sent).
- Document upload to **local disk** (type/size validation; no malware scan).
- Workflow: SA screening → Dean review → interview scheduling → final decision, with
  configurable SLAs surfaced but enforced via a manual artisan command.
- **Acceptance** publishes `AdmissionAccepted` → Identity mints a University ID and
  transitions R01→R02, creates the academic record, and applies automatic exemptions.
- Minimal **Calendar** support (interview slots) built just-in-time here.
- **Demo:** an applicant applies, is screened/approved/interviewed, accepted → Student.

### Phase 3 — Enrollment (SRS §4.4, §4.5, §4.6 partial)
- **Enrollment module:** `RegistrationRequest` → Advisor approval → `Enrollment`
  (Pending/Approved/Rejected). CH limits (min 6 / max 12, config-driven) + approaching-
  graduation and first-semester exceptions.
- Category rules: internal vs external elective (external needs advisor approval),
  PBL (MSc) vs Seminar (PhD) research course; level rules (500/600/700).
- Add/Drop/Late-Add/Withdrawal periods (Calendar) and **Course Status** accounting
  (Regular, Retake, Audit, W, FW, Incomplete, CGPA-Improvement, Transfer, Waive, Exempted).
- Re-registration / retake caps (max 1) and the elective-vs-core retake distinction.
- **Demo:** a Student registers courses, the Advisor approves, statuses are tracked.

### Phase 4 — Grading & GPA/CGPA (SRS §4.7)
- **Grading module:** instructor mark entry → auto letter-grade mapping (Appendix A) →
  sign/finalize → Department-Head review → `GradeFinalized`.
- Incomplete handling: I allowed at ≥70% completion; **artisan command** converts
  unresolved I→FW at the deadline; reminder logic recorded as in-app notifications.
- **GPA/CGPA calculation** — the SRS §4.7.2 gap; resolve the agenda in §5 first, then
  implement test-first.
- **Demo:** grades entered, signed, reviewed, finalized; GPA/CGPA displayed.

### Phase 5 — Academic Standing + Credit-Hour Summary + Exemption/Transfer/Waiver (SRS §4.8, §4.5, §4.6)
- **Derived read models** (on-demand): per-category Credit-Hour Summary (Appendix D
  counting) and Academic Standing (Good/Warning/Probation/Termination-Pending),
  recomputed on `GradeFinalized`/`WithdrawalRecorded`.
- **Exemption/Transfer/Waiver** (owned by Enrollment per ADR-0002): automatic exemption
  rules on enrollment (+ manual workflow), transfer (≤9 CH, min B, equivalency, 1-month
  window), waiver (level 400+, ≥80% coverage). None affect CGPA.
- **Termination workflow:** flags → notify → Dept. Council recommends → Academic Affairs
  Council decides → notify.
- **Demo:** finalize a grade → the Student's CH Summary and Standing update automatically.

### Phase 6 — Records & Transcript (SRS §4.9)
- **Records module:** transcript composition (Enrollments + Grades + CH Summary),
  official vs unofficial, rendered on-screen (bilingual).
- PDF export via Browsershot (seal/watermark/QR) is **optional** — add only if time
  allows; otherwise on-screen/print.
- **Demo:** view an unofficial transcript; SA issues an official one.

### Phase 7 — Reporting & polish (SRS §4.12)
- **Reporting module:** the 8 read-only cross-module reports (enrollment summary,
  application pipeline, GPA distribution, standing, graduation readiness, termination,
  audit log, course registration).
- i18n polish pass (Arabic coverage, RTL layout audit) and any deferred niceties.
- **Demo:** run reports; full EN↔AR toggle across the app.

---

## 4. Definition of done (per phase)

- Domain rules implemented **test-first** in **Pest** (unit + feature).
- `/api/v1` endpoints covered by Pest feature tests.
- **One Playwright smoke** exercising the phase's headline flow.
- **Vitest + RTL** tests for non-trivial frontend components only.
- Gates green: **deptrac** (module boundaries), **PHPStan/Larastan**,
  **eslint-plugin-boundaries**, **Pint**, ESLint/Prettier.
- Headline demo works manually; then your **manual review gate** before the next phase.

---

## 5. Phase 4 GPA/CGPA agenda (deferred from alignment)

SRS §4.7.2 is intentionally blank. Resolve these — via a focused `/domain-modeling`
drill at the start of Phase 4 — before implementing, and record the outcomes in
`CONTEXT.md` (+ an ADR on the counting interpretation if it proves contentious):

1. **Prep-course CGPA treatment** — letter-graded and counted (favoured by BR-56's
   "below C+ → retake"), or Pass/Fail and excluded?
2. **Formula** — confirm quality-points weighted average
   `Σ(points × CH) / Σ(CH)` over CGPA-counted courses.
3. **Different-elective retake penalty** — BR-55/Appendix D say *both* the failed
   elective and the replacement count in CGPA. Confirm and model.
4. **Earn threshold vs CGPA** — C+ (2.5) is required to earn a course's CH, but sub-C+
   grades still count in the CGPA denominator. Confirm.
5. **Incomplete → FW** denominator effects (excluded until resolved; FW excluded).
6. **Transfer / Waive / Exempted** — count toward Required CH, excluded from CGPA.
7. **Rounding / precision** — compute full precision, display 2 dp; rounding mode.

---

## 6. Execution flow

```
(this session) /grill-with-docs → ROADMAP.md + ADR-0005
   then, one phase at a time:
      /to-prd     → small PRD for the phase
      /to-issues  → vertical-slice issues
      /tdd        → build red-green-refactor
      manual review gate → next phase
```
