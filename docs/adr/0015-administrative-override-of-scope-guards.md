# Administrative override: System/Super Admin bypass faculty-scope and row-level guards

**Status:** accepted

Admissions enforces two access constraints *beyond* the permission matrix ([ADR-0008](0008-rbac-enums-static-matrix.md)):
the Dean queue and decision are **faculty-scoped** (a Dean acts only within their assigned
Faculty), and interview evaluation is **row-level** (only the assigned interviewer may
record a score). Phase 2.5 grants **R09 (System Admin)** and **R10 (Super Admin)** an
override: they reach every Application across all Faculties and Cycles through the *existing*
reviewer / Dean / interview screens, and may **record or override** an interview evaluation
score for any Interview. Admin writes are **attributed to the admin and written to the
Audit Log**.

## Considered options

- **Unscope the existing screens for admins, read and write (chosen).** Admins already hold
  every Permission (ADR-0008); this extends that to the scope and row-level guards that
  permissions don't cover, so oversight works through screens that already exist — no
  separate admin data surface to build.
- **A dedicated read-only admin data surface (rejected for now).** A larger, separate build;
  deferred to a future administration phase.
- **Bypass faculty-scope only, keep scoring strictly assigned-interviewer even for admins
  (rejected).** The narrower option, but the operators explicitly wanted admin oversight to
  *include* recording/overriding a score (an interviewer leaves; a correction is needed).

## Consequences

- This **reverses the interviewer-attestation model for admins specifically**: a score may
  now be authored by someone other than the assigned interviewer. The Audit Log entry
  (who / when) is what preserves accountability — and is precisely why an admin-authored
  score is audited where an interviewer scoring their own assignment is not.
- "Holds every Permission" and "sees all data" are now aligned for R09/R10 in Admissions.
  Controllers must check the is-admin bypass **consistently at each scope / row-level guard**,
  not only at the permission gate — a reviewer's own actions stay scoped.
- R09 and R10 are treated identically (as the permission model already does). If they ever
  need to diverge, this is the decision to revisit.
