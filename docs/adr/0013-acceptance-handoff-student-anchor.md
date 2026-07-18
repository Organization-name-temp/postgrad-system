# Admission acceptance hand-off: atomic mint via Identity\Public, Student anchor owned by Identity

**Status:** accepted

Accepting an Applicant must, in one indivisible step, flip their role, give them a
University ID, and create their student record (FR-ADM-008). Two questions had to be
settled: **how** Admissions triggers this across the module seam, and **who owns** the
resulting student record.

**Mechanism — atomic Public call, not an event-driven mint.** `Application::accept()`
calls a new `Identity\Public` contract (`StudentEnrollment::admit(...)`) **synchronously,
inside the same DB transaction** as the status change. `Admissions\Internal → Identity\Public`
is the same allowed edge Catalog already uses, so the dependency points the right way
(Admissions depends on Identity, the base module — never the reverse). The mint is also
where the R01→R02 transition is a **replace** (remove Applicant, add Student), the
University ID is generated (`{year}-{programCode}-{NNNN}`, Identity-owned counter), and an
Identity Audit Log entry is written for the role grant. Separately, an **`AdmissionAccepted`
domain event** is dispatched for *side-effects only* (the acceptance notification now, other
consumers later) — the project's first in-process domain event, used where events shine
(fire-and-forget) rather than for the critical mint.

**Ownership — Identity owns a thin `Student` anchor.** A `students` row, 1:1 with `User`,
holds `university_id`, an opaque `program_id` (ADR-0012, no FK), `enrollment_term`,
`admission_cycle_id`, `advisor_id`, `admitted_at`, and the identity snapshot copied from the
accepted Application. Identity owns only this *anchor*; the growing academic record
(Enrollments, Grades, CH Summary, Standing) belongs to Enrollment/Grading/Records
(ADR-0002) and references the anchor by id.

## Considered options

- **Atomic Public-call mint + event for side-effects (chosen).** Correct dependency
  direction, guaranteed atomicity of the mint, and still introduces the domain-event bus.
- **Pure event — Identity listens to `AdmissionAccepted` and mints (rejected).** Makes
  Identity depend on Admissions (dependency inversion), and puts a must-succeed mint inside
  a listener where a silent failure leaves an `Accepted` Application with no Student.
- **Pure direct call, no event (rejected).** Simplest, but drops the roadmap's event and
  forces every future consumer (notifications, analytics) to be wired by hand.
- **Student record owned by a new module / by Admissions / deferred to Phase 3 (rejected).**
  A new module is heavier than anything needs yet; Admissions shouldn't own post-admission
  state that outlives the Application; deferring leaves advisor assignment homeless and
  hands Phase 3 a half-built mint.

## Consequences

- `Identity\Public` grows a real service surface: `StudentEnrollment::admit`,
  `StudentDirectory::find`/`findByUniversityId`, `usersByRole`, `suggestAdvisor` (least-loaded
  R03, derived from advisee counts) — all exchanging DTOs, never Eloquent models.
- Role transition is **replace**: an admitted User no longer holds R01, so `/me` shows them
  purely as a Student. Their past Application still resolves (owned by `user_id`, not gated
  on holding R01); re-applying in a later cycle re-grants R01.
- Automatic exemptions (FR-ADM-008.2, BR-31/32/33) are **not** applied here — deferred to
  Phase 5 (Enrollment owns exemptions). The inputs are captured on the Application and
  `AdmissionAccepted` is the forward hook Phase 5 can consume.
- The mint is the first place the in-process event bus exists; later phases reuse it.
