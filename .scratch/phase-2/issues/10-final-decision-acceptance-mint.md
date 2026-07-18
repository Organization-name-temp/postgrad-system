# 10 — Final decision + acceptance mint + Student anchor

**What to build:** After the interview the Dean records the final decision. **Accepting mints a Student** — a University ID, the Student role in place of Applicant, an assigned advisor, and a Student record — all in one atomic step. Conditional and waitlist outcomes are held and later resolved by the Dean. A newly-admitted user signs in and sees their student identity. This is the phase's climax and the first cross-module write into Identity.

**Blocked by:** 09 — Interview slots + scheduling + evaluation

**Status:** ready-for-agent

- [ ] `Identity\Public` gains `StudentEnrollment::admit(...)`, `StudentDirectory::find`/`findByUniversityId`, `usersByRole(Role)`, and least-loaded `suggestAdvisor()` (all DTOs, no Eloquent). A `Student` anchor (1:1 User, opaque program ref, enrollment term, cycle ref, advisor, identity snapshot) is created at acceptance.
- [ ] The Dean (`applicant.decide`) records **Accepted / Conditionally Accepted (conditions) / Waitlisted (position) / Rejected (reason)** from `InterviewCompleted`; `ConditionallyAccepted → Accepted·Rejected` and `Waitlisted → Accepted·Rejected` resolutions work.
- [ ] On the terminal `Accepted` transition, `admit` runs **inside the same transaction**: mints University ID `{year}-{programCode}-{NNNN}` (per-(year, program) sequence, Identity-owned, unique), **replaces R01 with R02**, assigns the advisor, writes an Identity Audit Log entry. An `AdmissionAccepted` domain event is dispatched (its listener sends the acceptance Notification). **No `Student` exists unless the application is Accepted.**
- [ ] The decision form **pre-fills the least-loaded advisor**, overridable to any R03 user (validated to hold R03 at accept).
- [ ] `/me` (and `AuthUserResource`) surface the University ID / Student identity once a user holds R02; the accepted applicant re-signs-in as a Student.
- [ ] Pest feature tests: each decision path; the mint (role replace, University-ID format + uniqueness, Student resolvable, audit + notification rows, event dispatched via `Event::fake`); advisor validation; the two resolutions; `/me` student-aware. Unit tests: University-ID generation, least-loaded advisor. All gates green.
