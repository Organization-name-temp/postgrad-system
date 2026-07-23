# 09 — Register-for-student (SA / Advisor-composed)

**What to build:** The "Register Courses (For Student)" matrix capability (§3.2, `courses.register_for_student`), landed minimally. Staff can build and submit a registration on a student's behalf — but the two composers differ by design: Student Affairs composing for any student produces a normal Pending request that still goes to the student's Advisor, while an Advisor composing for their own advisee is **born Approved** (the composer is the approver, so making them approve their own paperwork is pure ceremony). Reuses the cart and every validator.

**Blocked by:** 06 (needs the cart, validators, advisor flow, and atomic mint).

**Status:** ready-for-agent

- [ ] Compose-on-behalf submission (`courses.register_for_student`, R03/R05): a student picker + the same cart from ticket 05, running the identical eligibility/CH/caps/clash/capacity/retake validators against the **target** student.
- [ ] **SA-composed** (any student) → a normal `Pending` RegistrationRequest routed to the student's Advisor (fires `registration.submitted`), exactly as a self-submission.
- [ ] **Advisor-composed for their own advisee** → the request is **born Approved** and mints Enrollments immediately in the same transaction (fires `registration.decided`, not a submit notification). An Advisor may only compose for their own advisees (scope-guarded); SA is unscoped.
- [ ] `/api/v1` compose endpoint (or a `on_behalf` path on submit) + Scribe examples; openapi + Orval regenerated, drift-clean.
- [ ] `features/enrollment` compose-for-advisee flow reusing the student cart with a student picker; visible to R03 (own advisees) and R05 (any). EN+AR, axe-clean.
- [ ] **Tests:** Pest API-feature — SA-composed lands Pending to the right advisor; advisor-composed for an advisee is born Approved and mints atomically; an advisor composing for a non-advisee is 403; validators apply to the target student; the correct notification fires in each path. Gates green.
