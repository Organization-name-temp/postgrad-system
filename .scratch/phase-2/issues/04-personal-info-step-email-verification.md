# 04 — Personal info (Step 1) + per-step save/resume + email verification

**What to build:** The applicant fills the Step-1 personal-information snapshot with inline validation and per-step "save & continue"/resume, then verifies their email with a signed link **shown on screen and logged** before they can proceed. The snapshot is per-cycle (re-applying later captures fresh data), not a shared profile.

**Blocked by:** 03 — Public self-registration

**Status:** ready-for-agent

- [ ] The Application carries the full Step-1 snapshot as typed columns + enums: bilingual name, national ID, phone, date of birth, nationality, gender, address (country/city/street), previous university/certificate/major, graduation date, previous degree (`BSc`/`MSc`/`PhD`), GPA + CGPA + GPA scale, E-JUST-graduate flag, applying-for degree (MSc/PhD), English test + score, native-Arabic-speaker.
- [ ] `(national_id, admission_cycle_id)` is unique (duplicate person in one cycle → 422).
- [ ] **Per-step "save & continue"** persists the Draft; resuming returns the applicant to the last saved step; validation errors are bilingual and inline.
- [ ] Submitting Step 1 issues a signed, 24h-expiring verification link **returned in the API response + written to the log** (no mailer bound); the applicant cannot proceed to documents until verified.
- [ ] Resend allowed **≤ 5 per 24h** (throttled). Verifying sets `email_verified_at` and moves `Submitted → EmailVerified`; a User whose email is already verified shortcuts `Draft → EmailVerified`.
- [ ] Frontend Step-1 form + verification step (shows the link). Pest feature tests: save/resume, validation incl. duplicate national-id-in-cycle, verification flow, resend throttle, pre-verified shortcut. All gates green.
