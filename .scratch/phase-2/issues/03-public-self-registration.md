# 03 — Public self-registration

**What to build:** The first unauthenticated door into the system. A prospective student registers with email, password, and name and — in one atomic act — gets a `User` with the **Applicant** role (R01) and a `Draft` **Application** in the currently-open Admission Cycle. Registration is refused when no cycle is open, and asks which cycle when more than one is open. Registering *is* starting the application; there is no orphan-account state.

**Blocked by:** 01 — Admissions module + Admission Cycle management

**Status:** ready-for-agent

- [ ] An **unauthenticated, rate-limited** registration endpoint creates a `User` (hashed password, R01 assigned) **and** a `Draft` Application in a single transaction, then logs the user in via Sanctum.
- [ ] Registration is refused with a clear "admissions are currently closed" response when no cycle is open; auto-assigns the cycle when exactly one is open; requires and validates a chosen (open) cycle when several are open.
- [ ] Email uniqueness is enforced at registration (duplicate → 422).
- [ ] The `Application` model and the `ApplicationStatus` enum are introduced (at least the `Draft` case); the Draft is owned by the registering User and tied to its cycle.
- [ ] A public **register / start-application** screen (open-cycle gated, cycle picker when needed); on success the applicant lands in their draft wizard. "Admissions closed" state renders when no cycle is open.
- [ ] Pest feature tests: registration creates `User(R01)` + `Draft`; closed-cycle refusal; multi-open cycle selection; duplicate email rejected; rate limit bites. Scribe/Orval regenerated; all gates green.
