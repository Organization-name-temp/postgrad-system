# Spec — Phase 2: Admissions (Applicant Registration & Admission)

**Status:** ready-for-agent
**Feature slug:** phase-2
**Phase:** 2 of the [Build Roadmap](../../docs/ROADMAP.md) (SRS §4.2 — Applicant Registration & Admission)
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [ROADMAP §Phase 2](../../docs/ROADMAP.md) · [SRS §4.2](../../docs/SRS.md) (FR-ADM-001…009), §4.10/§4.11 (notifications / calendar, partial) · [business-requirements](../../docs/business-requirements.md) (BR-01…12, BR-66…72, BR-73…76) · [architecture.md](../../docs/architecture.md) · [CONTEXT.md](../../CONTEXT.md) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0007](../../docs/adr/0007-hand-rolled-psr4-modules.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)/[0009](../../docs/adr/0009-mui-ui-foundation-design-system.md)/**[0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)**/**[0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md)**

---

## Problem Statement

Phase 0/0.5 gave the PRS an authenticated, themed, bilingual skeleton, and Phase 1 filled it with a browsable academic Catalog — but **there is still no way for anyone to actually apply to E-JUST, and no way for anyone to become a Student.** Every User today is seeded; the system has no front door for a stranger, and no pipeline for staff to turn an application into an admission.

From the point of view of the people who touch the system:

- **A prospective student** has no account and no way to make one. They cannot start an application, tell the system who they are, verify their email, upload their certificates, choose a Program to apply to, submit, or find out what happened to their application. There is no path from "interested outsider" to "admitted Student."
- **Student Affairs staff** have no queue of applications to screen, no way to check completeness, add notes, or move an application forward, return it for fixes, or reject it — and no visibility of how long applications have been waiting.
- **A Dean** has no way to review the applications for their Faculty, approve promising ones for interview, or reject the rest.
- **An interviewer** has nowhere to record how an interview went.
- **The Dean (as decider)** cannot record a final admission decision, and — critically — even a decision to accept has nowhere to go: no University ID is minted, no role changes, no Student record is created, so an "accepted" applicant is still just an applicant.
- **Every actor** is blind to what is happening: no notifications tell an applicant their application was returned or accepted, or remind an interviewer of a scheduled interview.
- **A developer** has no Admissions or Notifications module to build the rest of the student lifecycle on, and no realistic in-flight application data to develop and demo against.

## Solution

Build two new modules dropped into the existing skeleton: **Admissions** (the application workflow) and **Notifications** (cross-cutting in-app messaging), both following the modular-monolith `Public`/`Internal` shape.

A stranger can **register themselves** — the first unauthenticated door into the system — which in one atomic act mints a User with the **Applicant** role and starts their `Draft` **Application**, but only while an **Admission Cycle** is open. They complete a **multi-step wizard** (Personal Info → Email Verify → Documents → Program Selection), where email verification uses a signed link **shown on screen and logged** (no mail sent), documents upload to the **private local disk**, and Program selection **reuses the Phase-1 catalog tree** and is validated against a degree-qualification rule. On **finalize**, the Application enters a hand-rolled **13-state workflow**: Student Affairs screens it (approve / return-for-revision / reject), an auto-forward sends approved ones to the **Faculty's Dean**, the Dean approves for **interview**, Student Affairs schedules the applicant into an **interview slot**, the assigned **interviewer** records an evaluation, and the Dean records the **final decision** (Accepted / Conditionally Accepted / Waitlisted / Rejected). Every transition is timestamped, attributed, optionally noted, and recorded in an **append-only timeline** that is both the applicant's visible status history and the decision audit trail.

**Acceptance is the climax.** On the terminal `Accepted` transition, Admissions calls Identity **synchronously, in the same transaction** ([ADR-0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md)) to mint the Student: create the thin **`Student` anchor**, generate a **University ID** (`{year}-{programCode}-{NNNN}`), **replace** the Applicant role with Student (R01→R02), assign a **least-loaded Advisor**, and write an Identity Audit Log entry. A first-ever in-process **`AdmissionAccepted` domain event** fires for side-effects (the acceptance notification). Programs are referenced across the module seam by **opaque id through a `Catalog\Public` read-DTO facade with no foreign key** ([ADR-0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)).

Throughout, an in-app **Notification** with a bilingual-rendered type+payload reaches each actor's **polled bell** (filling the slot Phase 0.5 reserved). Configurable SLAs are surfaced as on-demand aging on the SA dashboard and nudged by **manual artisan commands** (overdue flagging, interview reminders) — no automatic escalation. Deans are routed by a new **Dean↔Faculty assignment** (Identity-owned) maintained through a minimal **admin surface gated on the R09/R10 role**. A deterministic **seeder** stands up an open Fall 2026 cycle with one Application resting in every status so all screens demonstrate immediately.

## User Stories

**Registering & starting an application (prospective student → Applicant)**

1. As a prospective student, I want to register with my email, password, and name and have that immediately start my application, so that I can begin applying without a separate account-creation step.
2. As a prospective student, I want registration to be possible only while an Admission Cycle's window is open, so that I apply into a real intake rather than a void.
3. As a prospective student, I want to be told clearly "admissions are currently closed" when no Cycle is open, so that I understand why I cannot start.
4. As a prospective student applying while more than one Cycle is open, I want to choose which Cycle (e.g. Fall 2026 vs Spring 2027) I am applying to, so that my application is filed against the right intake.
5. As an Applicant, I want my email to be unique in the system and a duplicate application (same national ID) within the same Cycle to be prevented, so that I cannot accidentally apply twice for one intake (BR-03).
6. As a rejected Applicant, I want to apply again in a later Cycle, so that a past rejection does not bar me forever.

**Completing the multi-step wizard (Applicant — `application.submit` / `documents.upload`)**

7. As an Applicant, I want to fill in my personal information (names in English and Arabic, contact, national ID, date of birth, nationality, gender, address, previous degree/university/major/GPA, English proficiency, E-JUST-graduate flag, degree I'm applying for), so that the university knows who I am and my background.
8. As an Applicant, I want each step saved when I choose "save & continue" and to resume later at the step I left, so that I don't lose progress or have to finish in one sitting (FR-ADM-001.2).
9. As an Applicant, I want real-time, inline validation on each field, so that I can correct mistakes as I go (FR-ADM-001.3).
10. As an Applicant, I want a verification link shown to me on screen (and logged) after I submit step 1, and to not be able to proceed to documents until I have verified, so that my email is confirmed (FR-ADM-002).
11. As an Applicant, I want to re-request the verification link up to five times a day, so that I can recover if I miss it (FR-ADM-002.4, BR-72).
12. As an Applicant whose email is already verified, I want to skip the verification step, so that I don't repeat work.
13. As an Applicant, I want to upload each required document (graduation certificate, transcript, national ID/passport, personal photo) within its size/format limits, so that my application is complete (FR-ADM-003, BR-66/69).
14. As an Applicant who selected an English test, I want to be required to upload the IELTS/TOEFL certificate, and as a PhD applicant to upload my MSc thesis, so that conditional documents are enforced correctly (BR-67).
15. As an Applicant, I want to upload optional documents (CV, up to two recommendation letters, up to five additional certificates), so that I can strengthen my application (BR-68).
16. As an Applicant, I want to preview, replace, or remove any uploaded document before I finalize, so that I can fix mistakes (FR-ADM-003.5).
17. As an Applicant, I want to browse E-JUST's Programs grouped by Faculty in a tree and pick one primary and optionally one alternative Program, so that I apply where I want (FR-ADM-004, BR-07).
18. As an Applicant, I want the system to reject a Program my previous degree doesn't qualify me for (BSc→MSc, MSc→MSc/PhD, PhD→PhD), so that I don't apply to something I'm ineligible for (FR-ADM-004.4).
19. As an Applicant, I want to see a confirmation summary of my chosen Program(s) and my details before I finalize, so that I submit deliberately (FR-ADM-004.5).
20. As an Applicant, I want finalizing to be blocked until a primary Program is chosen and all required (and conditionally-required) documents are uploaded, so that only complete applications reach review.

**Tracking status & interviews (Applicant — `application.view_status`)**

21. As an Applicant, I want a visual timeline/progress view of my application's status with each change timestamped, so that I always know where I stand (FR-ADM-009).
22. As an Applicant whose application was returned for revision, I want to see the reviewer's instructions, fix my application, and resubmit, so that I can address what was wrong (FR-ADM-005.3).
23. As an Applicant, I want to be notified and see the interview details (date/time, interviewer, in-person location or virtual link) when my interview is scheduled, so that I can attend (FR-ADM-007.3).
24. As an Applicant, I want to confirm my interview or request exactly one reschedule into another open slot, so that I can accommodate a genuine conflict without gaming the process (FR-ADM-007.4, BR-11).
25. As an Applicant, I want to be notified of the final decision (accepted, conditionally accepted, waitlisted, or rejected with reason), so that I know the outcome (FR-ADM-008.3).
26. As an accepted Applicant, I want to receive my University ID and be able to sign in as a Student, so that I can begin my studies.

**Student Affairs screening (`application.screen` / `interview.schedule`)**

27. As Student Affairs staff, I want a queue of finalized applications awaiting screening, so that I know what to work on (FR-ADM-005.1).
28. As Student Affairs staff, I want each queued application to show how long it has been waiting and to be flagged when it exceeds the screening SLA, so that I can prioritise and not let applications age out (FR-ADM-005.4/5).
29. As Student Affairs staff, I want to open an application and see all its details and uploaded documents, so that I can assess completeness and authenticity (FR-ADM-005.2).
30. As Student Affairs staff, I want to approve an application for Dean review, return it for revision with instructions, or reject it — each with a note, so that I can move it to the right next state.
31. As Student Affairs staff, I want approving an application to forward it to the relevant Faculty's Dean without a separate step, so that the pipeline flows.
32. As Student Affairs staff, I want to open and close Admission Cycles and set their windows, so that I control when applications are accepted.
33. As Student Affairs staff, I want to create interview slots (date/time, assigned interviewer, in-person location or virtual link), so that approved applicants can be scheduled.
34. As Student Affairs staff, I want to schedule an approved applicant into an open interview slot, so that their interview is booked and they are notified.

**Dean review & decision (`application.screen` / `applicant.decide`)**

35. As a Dean, I want to see only the SA-approved applications for **my** Faculty, so that I review what is actually mine (FR-ADM-006.1).
36. As a Dean, I want to open an application with Student Affairs' notes and approve it for interview scheduling or reject it with a reason, so that I gate who proceeds (FR-ADM-006.2).
37. As a Dean, I want the system to refuse my action on an application outside my Faculty, so that routing integrity is enforced, not assumed.
38. As a Dean, I want to record the final decision after the interview — Accepted, Conditionally Accepted (with conditions), Waitlisted (with position), or Rejected (with reason), so that the outcome is captured (FR-ADM-008.1).
39. As a Dean accepting an applicant, I want an Advisor pre-selected for me (the least-loaded eligible Advisor) but overridable to any Advisor, so that assignment is fair yet within my control.
40. As a Dean, I want to later resolve a Conditionally Accepted applicant to Accepted (conditions met) or Rejected, so that conditional offers reach a real outcome.
41. As a Dean, I want to later resolve a Waitlisted applicant to Accepted (a spot opened) or Rejected (the cycle closed), so that waitlists don't linger indefinitely.

**Interviewing (`interview.schedule` context; row-level interviewer)**

42. As an assigned interviewer, I want to record my evaluation score and notes for the applicant I am interviewing, so that the decision is informed (FR-ADM-007.6).
43. As an interviewer, I want to be blocked from evaluating an interview I am not assigned to, so that evaluations are trustworthy.
44. As Student Affairs, I want to mark an interview as completed so the application can move to decision, so that the workflow advances after the interview happens.

**Notifications (all actors)**

45. As any User, I want a notifications bell showing my unread count that I can open to read my notifications, so that I stay informed of events that concern me (FR-NTF-001).
46. As any User, I want to mark a notification (or all of them) as read, so that I can clear my unread count.
47. As an Arabic-speaking User, I want every notification rendered in my language, so that messages are readable regardless of the language they were created in.
48. As an interviewer, I want reminders before a scheduled interview, so that I don't forget it (FR-ADM-007.5 — in-app only in this build).

**Administration (System/Super Admin — R09/R10 role-gated)**

49. As an administrator, I want to assign a Dean to a Faculty (and unassign), so that Dean review routing reflects the real org (FR-ADM-006.1).
50. As an administrator, I want the assignments screen and its endpoints restricted to admins, so that org routing cannot be changed by ordinary staff (BR-75).

**Developer & maintainer**

51. As a developer, I want Admissions and Notifications delivered as new modules with a `Public`/`Internal` split, so that they follow the modular-monolith pattern.
52. As a maintainer, I want deptrac to enforce that Admissions reaches Catalog, Identity, and Notifications only through their `Public` facades (never `Internal`), so that the first multi-edge cross-module boundary is proven.
53. As a developer, I want Admissions to reference Catalog Programs by opaque id through a Catalog read facade with no foreign key, so that the module seam holds at the database as well as in code ([ADR-0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)).
54. As a developer, I want the acceptance mint to be a single atomic Identity `Public` call and the `AdmissionAccepted` event to carry only side-effects, so that an Accepted application can never exist without a Student ([ADR-0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md)).
55. As a developer, I want the Admissions and Notifications endpoints reflected in the committed OpenAPI spec and generated typed client, so that types never drift from the server.
56. As a developer, I want the `admissions`, `admin`, and notifications-bell frontend code added to the boundary ruleset, so that features can't import each other's internals.
57. As a developer, I want a deterministic bilingual seeder with an open Cycle and an Application in every status, so that I can develop and demo every screen immediately.

## Implementation Decisions

**Modules & boundaries** ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md), [ADR-0007](../../docs/adr/0007-hand-rolled-psr4-modules.md))
- Two new modules following the Identity/Catalog shape: **`Admissions`** and **`Notifications`** (`Public/` = `Http/routes.php` + read Resources + the cross-module contracts other modules may call; `Internal/` = Models, Actions, Controllers, Policies, Migrations, Enums, the state machine, the console commands).
- **deptrac** gains `Admissions` and `Notifications` layers — the first **multi-edge** phase. Allowed: `Admissions → Catalog\Public`, `Admissions → Identity\Public`, `Admissions → Notifications\Public`; `App → Admissions\Public`, `App → Notifications\Public`. Every `Internal` stays sealed. Notifications depends on nothing but Identity\Public (user ids).
- **Identity gains a real `Public` service surface** (previously only enums + `AuditLogger` + health): `StudentEnrollment::admit(command)`, `StudentDirectory::find/findByUniversityId`, `usersByRole(Role): list<UserSummary>`, `suggestAdvisor(): ?UserSummary`, and the Dean↔Faculty assignment reads (`facultiesForDean` / `deansForFaculty`). All exchange **DTOs, never Eloquent models**.

**Cross-module reads** ([ADR-0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md))
- Admissions stores `primary_program_id` / `alternative_program_id` and Identity's `Student` stores `program_id` as **plain reference values with no FK to `programs`**. Program data is read through a new **`Catalog\Public` read facade** (`ProgramCatalog::find(id): ?ProgramSummary`, plus a list for reviewer screens) returning read DTOs (id, code, name_en/ar, `DegreeLevel`, department + faculty names). This **amends ADR-0010's** "facade waits for Phase 3": Admissions is Catalog's real first consumer.
- The applicant-facing Program tree for *selection* reuses the Phase-1 `GET /api/v1/catalog/tree` endpoint for display; the facade is for backend validation, storing a valid id, and rendering summaries on reviewer screens.

**Acceptance hand-off** ([ADR-0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md))
- `Application::accept()` calls `Identity\Public::StudentEnrollment::admit(...)` **synchronously inside the same DB transaction** as the status change. `admit` creates the `Student` anchor, generates the University ID (`{year}-{programCode}-{NNNN}`, per-(year, program) sequence computed under a row lock, Identity-owned), **replaces** R01 with R02, and writes an Identity Audit Log entry. An **`AdmissionAccepted`** in-process domain event (the project's first) is then dispatched for side-effects only; its Phase-2 listener sends the acceptance Notification.
- The mint fires **only** on the terminal `Accepted` transition — never on `ConditionallyAccepted`/`Waitlisted`.
- **Automatic exemptions are deferred to Phase 5** (Enrollment owns exemptions). The eligibility inputs (English test + score, E-JUST-graduate flag, previous degree) are captured on the Application; `AdmissionAccepted` is the forward hook.

**Schema shape** — decision-encoding sketch from the design grill; **column details are indicative, not prescriptive**:
```
-- Admissions module
admission_cycles     (id, intake_term ∈ {Fall, Spring}, year, name_en, name_ar,
                     opens_at, closes_at, is_active,   UNIQUE(intake_term, year))
applications         (id, user_id → users, admission_cycle_id → admission_cycles,
                     status ∈ ApplicationStatus(13),
                     -- Step 1 personal-info snapshot (per-cycle, not a shared profile):
                     name_en, name_ar, national_id, phone, date_of_birth, nationality,
                     gender ∈ {Male, Female}, address_country, address_city, address_street,
                     previous_university, graduation_certificate_name, graduation_date,
                     previous_degree ∈ {BSc, MSc, PhD}, previous_major,
                     graduation_gpa, graduation_cgpa, gpa_scale ∈ GpaScale,
                     is_ejust_graduate, applying_for_degree ∈ {MSc, PhD} (Catalog DegreeLevel),
                     english_test ∈ EnglishTest, english_score, native_arabic_speaker,
                     -- Step 4 program selection (opaque Catalog refs, NO FK):
                     primary_program_id, alternative_program_id NULLABLE,
                     -- decision extras:
                     conditions TEXT NULL, waitlist_position SMALLINT NULL,
                     submitted_at, finalized_at,
                     UNIQUE(national_id, admission_cycle_id))
application_documents (id, application_id → applications, document_type ∈ DocumentType,
                     ulid UNIQUE, original_filename, stored_path, mime, size_bytes, uploaded_at)
application_status_transitions   -- APPEND-ONLY (timeline + decision audit)
                     (id, application_id → applications, from_status NULL, to_status,
                     actor_user_id → users NULL, note TEXT NULL, occurred_at)
interview_slots      (id, scheduled_at, duration_minutes, interviewer_id → users,
                     mode ∈ {InPerson, Virtual}, location NULL, meeting_link NULL)
interviews           (id, application_id → applications UNIQUE, interview_slot_id → interview_slots,
                     status ∈ {PendingConfirmation, Confirmed}, reschedule_count DEFAULT 0,
                     evaluation_score NULL, evaluation_notes NULL, completed_at NULL)

-- Identity module (additions)
students             (id, user_id → users UNIQUE, university_id UNIQUE,
                     program_id (opaque Catalog ref, NO FK), enrollment_term,
                     admission_cycle_id (opaque ref, NO FK), advisor_id → users,
                     name_en, name_ar, national_id, date_of_birth, nationality, gender,
                     admitted_at)
faculty_dean_assignments (id, user_id → users, faculty_id (opaque Catalog ref, NO FK),
                     UNIQUE(user_id, faculty_id))

-- Notifications module
notifications        (id, user_id → users, type ∈ NotificationType, data JSONB,
                     read_at NULL, created_at)
```
- **Status machine (hand-rolled):** an `ApplicationStatus` enum (13 cases) with a **static allowed-transitions map** (same style as `Permission::roles()`); named action methods on the `Application` model assert legality (illegal → **409**), apply business guards (finalize requires primary program + required docs), and append a `application_status_transitions` row. `SAApproved` is transient (approve auto-advances to `UnderDeanReview` in one action). Extra exits beyond the SRS diagram: `ConditionallyAccepted → Rejected`, `Waitlisted → Rejected`.
- **Enums (Admissions-owned):** `ApplicationStatus`, `IntakeTerm` (Fall/Spring), `Gender`, `PreviousDegree` (BSc/MSc/PhD — extends the SRS's BSc/MSc to support PhD→PhD), `GpaScale`, `EnglishTest` (IELTS, TOEFL iBT/PBT, PTE, Cambridge C1/C2/B2, Duolingo, None — per the MVP UML supported-values list), `InterviewMode`, `InterviewStatus`, and **`DocumentType`** which carries its own rules (`requirement` required/conditional/optional, `maxSizeMb`, `allowedMimes`, `maxCount`). `applying_for_degree` reuses `Catalog\Public\DegreeLevel`. `NotificationType` is Notifications-owned.
- **Degree qualification:** order BSc < MSc < PhD; a Program qualifies iff `0 ≤ targetOrdinal − previousOrdinal ≤ 1`. Enforced at program selection and again at finalize (mismatch → 422, bilingual message); `applying_for_degree` must match the primary Program's `DegreeLevel`.
- **Documents:** private `local` disk under `storage/app` (never `public`); **no malware scan, no encryption-at-rest** (ADR-0005); ULID public identifier; served by a Sanctum-authed **streaming route** guarded by a policy (the owning Applicant, or a reviewer holding `ScreenApplications`). Editable only while the Application is applicant-editable (Draft/Submitted/EmailVerified/ReturnedForRevision).
- **Email verification:** reuse Laravel's built-in signed, expiring (24h, `email_verification_hours` config) verification on `users.email_verified_at`; the URL is returned in the API response and logged (no mailer). Resend throttled to 5/24h via a keyed rate limiter. Verification drives `Submitted → EmailVerified`; a pre-verified email shortcuts `Draft → EmailVerified`.
- **SLAs:** config-driven `sa_screening_sla_days` / `dean_review_sla_days` (default 5). Aging is **derived on-demand** from the `occurred_at` of the transition that entered the review status — never stored. Two **manual artisan commands** (`admissions:flag-overdue`, `admissions:send-interview-reminders`) **only emit Notifications** (no state change, no escalation); overdue uses **business days** excluding the Egyptian weekend (Fri/Sat), no holiday calendar.
- **Advisor assignment:** `suggestAdvisor()` returns the **least-loaded** R03 user (fewest current `students.advisor_id`, tie-broken by id — derived, no stored cursor). The Accept form pre-fills it; the Dean may override to any R03 user (list via `usersByRole`); validated to hold R03 at accept.
- **Dean↔Faculty scoping:** Identity owns `faculty_dean_assignments`; the Dean queue lists SA-approved applications whose Program's faculty (resolved `application → program_id → facultyId` via `ProgramCatalog`) is among the Dean's assigned faculties; out-of-faculty actions → **403**. Assignments are **seeded** and maintained through a minimal admin surface **gated on the R09/R10 role** (the first role-gated, not permission-gated, surface — no new permission invented, ADR-0008 intact).

**API contract** (inherits Phase 0 conventions: success `{ data, meta }` via Resources; errors native `{ message, errors }`; 422 validation, 401/403 auth, 404 not found, **409 illegal transition**)
- **Public/unauthenticated:** register-and-start-application (gated on an open Cycle; rate-limited) and an open-cycle lookup for the registration screen. Laravel's signed email-verify + throttled resend.
- **Applicant-owned:** get/patch (per-step save) my Application; document add/remove/stream; finalize (→ `UnderSAReview`); interview confirm/reschedule.
- **Reviewer:** application list (SA queue; Dean queue faculty-scoped) with derived aging; SA screen action (approve/return/reject + note); Dean approve-for-interview/reject; Dean decision (accept[+advisor]/conditional/waitlist/reject) and the conditional/waitlist resolutions; interview-slot CRUD + schedule + complete; interviewer evaluation (row-level).
- **Cycles:** CRUD under `ManageAcademicCalendar`.
- **Notifications:** list (paginated, newest-first), unread-count, mark-read, mark-all-read.
- **Identity additions:** admin faculty-dean assignment list/assign/unassign (R09/R10 role); an advisor list + suggestion for the decision form; `/me` (and `AuthUserResource`) extended so a User holding R02 surfaces their University ID / `Student` anchor.
- **RBAC:** reuse the fixed §3.2 matrix ([ADR-0008](../../docs/adr/0008-rbac-enums-static-matrix.md)) — `application.submit` (Applicant), `documents.upload`, `application.view_status`, `application.screen` (SA/Dean), `interview.schedule` (SA/Dean), `applicant.decide` (Dean), `calendar.manage` (cycles). **No new permissions**; the only role-based gate is the admin assignment surface.

**Frontend** ([ADR-0009](../../docs/adr/0009-mui-ui-foundation-design-system.md); tokens/shell/StatusChip from Phase 0.5)
- New **`features/admissions`** and a small **`features/admin`**; added to the `eslint-plugin-boundaries` ruleset.
- **Applicant:** a public register/start entry (open-cycle gated); the multi-step **wizard** with **per-step "save & continue"** and resume; email-verify step showing the link; document upload with preview/replace/remove; Program selection **reusing the Phase-1 catalog tree** (`@mui/x-tree-view`); a review/confirm step; and a **status timeline** (read-only panel over the transition log, same shape as Catalog's `VersionHistoryPanel`) with a colocated `statusToChip` map driving `<StatusChip>` across all 13 statuses.
- **Staff:** SA screening queue (aging/overdue), application detail + document viewer, screen actions; Dean queue (faculty-scoped) + review; interview scheduling + interviewer evaluation; the Dean decision form (advisor pre-filled/overridable). Cycle management.
- **Admin:** an **Administration** nav section (visible only to R09/R10) with the Faculty→Dean assignment screen.
- **Shell:** the **polled notifications bell** fills the Phase-0.5 slot; notifications render bilingually from type+payload via an `admissions`/`notifications` i18n namespace.
- Endpoints documented with Scribe, consumed through the Orval-generated TanStack Query client; `openapi.json` + client committed and drift-checked.

**Seeder** (deterministic, runs after the Phase-1 catalog seeder)
- One **open `Fall 2026`** Admission Cycle (window spanning the current date) and optionally a closed past Cycle to prove the "closed" gate.
- Staff depth beyond Phase 0's one-per-role: **3–4 Advisors**, **a Dean per Faculty** with seeded `faculty_dean_assignments`, and a couple of interviewers; SA stays single.
- **One Application resting in each visible status** (Draft … Rejected), deterministic; the `Accepted` one is the deep anchor that produced a real `Student` (University ID, advisor, R02).
- Seeded **interview slots** (open ones) and a small **sample PDF/image** on the local disk referenced by seeded document rows. The seeder must **not** consume the demo applicant's email/national ID (the smoke registers its own).

## Testing Decisions

**What makes a good test here:** assert **external, observable behavior only** — HTTP status codes and JSON bodies, notification rows via the notifications endpoint, the status timeline via the API, `/me` reflecting R02 + University ID after acceptance, the rendered wizard/queue/bell, the RTL flip — never Models, Actions, the state machine's internals, or other module internals, which are swappable behind the `Public` facades ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md)/[0007](../../docs/adr/0007-hand-rolled-psr4-modules.md)). Tests are written **test-first** (Pest) per the roadmap DoD. Seams reuse Phase 0/1 prior art; **exactly one new seam** (console commands) is introduced, at the highest point.

**Confirmed seams:**

1. **Primary — HTTP API feature tests (Pest) against `/api/v1`** (prior art: [AuthorizationTest.php](../../backend/tests/Feature/Identity/AuthorizationTest.php) and the Phase-1 catalog feature tests; `RefreshDatabase` + `Sanctum::actingAs` + factories; `UploadedFile::fake()` for documents; `Event::fake` for the event). The highest backend seam; carries the majority: self-registration (open-cycle gate, duplicate `(national_id, cycle)` → 422, rate-limit), email verification + throttled resend, document upload (type/size/cardinality, conditional-required at finalize), program selection + the qualification rule (422), **every status transition and its RBAC** (illegal transition → 409; SA/Dean/decider actions; return-for-revision loop), interview scheduling / one-reschedule / row-level evaluation / completion, **the acceptance mint observed end-to-end** (accept → response carries the University ID; `/me` as the now-Student shows R02 and the University ID; the `Student` is resolvable; an Identity Audit Log row appears; an acceptance Notification row appears; `AdmissionAccepted` dispatched), **Dean faculty-scoping** (out-of-faculty action → 403), the notifications endpoints (list/unread-count/mark-read), and the admin assignment endpoints (allowed for R09/R10, 403 otherwise).
2. **Lower — targeted Pest unit** (prior art: [PermissionMatrixTest.php](../../backend/tests/Unit/PermissionMatrixTest.php), the `CreditHours` value-object test). Only the pure logic worth an authoritative direct test rather than HTTP round-trips: the `ApplicationStatus` **transition map** (which transitions are legal), **University-ID generation** (format + per-(year, program) sequence), the **business-days** SLA helper (Fri/Sat weekend), **least-loaded advisor** selection (fewest advisees, tie-break), the **degree-qualification** ordinal check, and the **`DocumentType`** rules (requirement/size/cardinality).
3. **★ New seam — console-command feature tests** (prior art: none — Phase 0/1 had no commands; standard Laravel `$this->artisan(...)`). Invoke `admissions:flag-overdue` and `admissions:send-interview-reminders` against seeded state and assert the **Notification rows they produce** (and that they change no application status). Tested at the highest point — command invocation → observable DB side-effects — not by reaching into command internals.
4. **Cross-stack — one comprehensive multi-role Playwright smoke** (the phase's headline flow, per DoD; prior art: [headline.spec.ts](../../frontend/e2e/headline.spec.ts), reusing `login()`, `expectNoSeriousA11y()`). Against the **real stack** (Postgres + the Phase-1 + Phase-2 seeders): a fresh applicant registers into the open Cycle → completes the wizard (verify link on screen, upload a doc, pick a Program) → finalizes → **SA** approves → **Dean** approves for interview → SA schedules a slot → **interviewer** records an evaluation → SA completes → **Dean** accepts (advisor pre-filled) → the applicant re-signs-in as a **Student** with a visible University ID; `@axe-core` reports no serious/critical violations on the touched screens.
5. **Frontend — Vitest + RTL, sparingly** (DoD: non-trivial components only; prior art: [StatusChip.test.tsx](../../frontend/src/shared/ui/StatusChip.test.tsx), [SideNav.test.tsx](../../frontend/src/app/SideNav.test.tsx)). The components with real logic: the wizard's per-step save/validation gating, the status-timeline + `statusToChip` map (each status → the right tone/label), and the polled notifications bell (unread count renders; mark-read clears it).

**Boundary gate (structural, not a behavioral seam):** `deptrac.yaml` gains `Admissions` and `Notifications` layers with `Admissions → {Catalog, Identity, Notifications}\Public` allowed and all `Internal` sealed; `deptrac analyse` runs in `composer gate`. This is the first phase with **multiple** cross-module edges.

**Modules under test:** Admissions and Notifications (API feature tests + console-command tests + the targeted units); Identity's new `Public` mint/assignment surface (via the acceptance and admin feature tests); the frontend `admissions`/`admin` features and app shell via the Vitest components and the Playwright smoke.

## Out of Scope

Deferred per [ADR-0005](../../docs/adr/0005-localhost-learning-build.md) and the design grill; Phase 2 explicitly does **not** include:

- **Real email/SMS delivery** — the verification link and all notices are in-app/on-screen/logged only; email/SMS channels (FR-NTF-001, BR-70) stay deferred.
- **Malware scanning and encryption-at-rest** of uploads (FR-ADM-003.2/.3) — local disk only.
- **Automatic exemption application** (FR-ADM-008.2, BR-31/32/33) — deferred to **Phase 5** (Enrollment owns exemptions); inputs are captured here and `AdmissionAccepted` is the hook.
- **Automatic SLA enforcement/escalation** — the artisan commands only flag/notify; the workflow advances only by human action.
- **The full academic calendar** (§4.11 — semester/registration/add-drop/withdrawal periods) — only `InterviewSlot` is built now.
- **GPA-scale/percentage conversion logic** — the scale and raw values are captured; no conversion engine.
- **Structured conditional-acceptance conditions** — `conditions` is free text; no rules engine.
- **Department-scoped Advisor pools** — Advisors are assigned globally by least-load; the org-assignment mapping is built for Deans↔Faculties only (advisor↔department joins the future "org assignments" feature).
- **Runtime editing of Dean↔Faculty assignments beyond the minimal admin surface**, role-assignment management UI, and general org-assignment administration — a later admin feature; Phase 2 seeds assignments and ships only the Dean↔Faculty screen.
- **Account hardening** (lockout, CAPTCHA, password policy, MFA/SSO — BR-77/78, FR-SEC) — self-registration is about the *flow* existing, not production abuse resistance (ADR-0005).

## Further Notes

- **New modules, first multi-edge boundary, first domain event, first console commands, first role-gated surface, first unauthenticated route** — Phase 2 introduces more architectural firsts than any phase since Phase 0. Each is deliberate and recorded (ADRs 0012/0013; the role-gating convention noted in ROADMAP §Phase 2).
- **New dependencies:** none pinned — `@mui/x-tree-view` (reused for program selection) already arrived in Phase 1; no new backend packages (state machine, notifications, and verification are hand-rolled / framework-native).
- **Glossary:** Phase 2 added the Admissions/Notifications vocabulary to [CONTEXT.md](../../CONTEXT.md) — **Admission Cycle, Intake Term, Application (revised), Application Status, Status Transition, Interview Slot, Interview, University ID, Student anchor, Notification**. Use this vocabulary in code, tests, and UI.
- **`/me` becomes role-aware of Student:** after acceptance the User holds R02 (R01 removed); `/me` and the SPA must surface the `Student` anchor (University ID) so the newly-admitted user sees their student identity. The Phase-0 role-swap plumbing (`assignRole`) gains a matching removal.
- **Supersedes nothing in Phase 1**, but is the first real **consumer** of the Phase-1 Catalog (via `ProgramCatalog`) and of the Phase-0 Identity RBAC/audit plumbing (via `StudentEnrollment`).
- **Next step:** `/to-issues` to break this spec into vertical-slice implementation tickets under `.scratch/phase-2/issues/`, then `/tdd` red-green-refactor per ticket, then the manual review gate before Phase 3. This work builds off `main` on a `phase-2-admissions` branch (per the project's branch-per-phase pattern), after the Phase-1 review gate merges.
