# 03 — Show credit decisions and Credit-Hour Summary

**What to build:** Complete the current Transcript with authoritative academic-progress totals and effective credit-decision details, separately from Courses the Student actually took.

**Blocked by:** 02 — View current unofficial Transcripts.

**Status:** complete

- [x] Consume AcademicStanding's CreditHourSummaries and the Student's bound Curriculum Version; display per-category and total Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH unchanged from their owner.
- [x] Do not recreate allocation, category caps, Remaining calculations, or CGPA effects in Records. Preserve raw Earned CH alongside target-aware satisfaction when an effective Exemption releases a completed Course.
- [x] Show effective Exemptions, Transfers, and Waivers in a separate section with target Course, recognized/released CH, type, and approval date; no synthetic Enrollments.
- [x] Transfers also show source institution/Course, original Grade, and normalized E-JUST Grade; Waivers show prior E-JUST Course/Grade; Exemptions show qualification-based reason.
- [x] Revoked decisions and under-review requests are excluded from the current effective-decision section. Evidence attachments, private document identifiers/URLs, and reviewer notes are excluded.
- [x] EN/AR screens and print layouts display the full decision section and summaries using owner Public DTOs and generated client types, preserving ticket 02's scopes.
- [x] HTTP tests cover each decision type, revocation, source detail, Exemption-plus-earned-credit accounting, category totals, null values, and absence of private evidence; focused frontend tests cover meaningful rendering behavior.
- [x] API artifacts and bilingual messages are synchronized; relevant tests and affected gates pass, with concrete reasons for any gate not run.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
