# 03 — Show credit decisions and Credit-Hour Summary

**What to build:** Complete the current Transcript with authoritative academic-progress totals and effective credit-decision details, separately from Courses the Student actually took.

**Blocked by:** 02 — View current unofficial Transcripts.

**Status:** ready-for-agent

- [ ] Consume AcademicStanding's CreditHourSummaries and the Student's bound Curriculum Version; display per-category and total Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH unchanged from their owner.
- [ ] Do not recreate allocation, category caps, Remaining calculations, or CGPA effects in Records. Preserve raw Earned CH alongside target-aware satisfaction when an effective Exemption releases a completed Course.
- [ ] Show effective Exemptions, Transfers, and Waivers in a separate section with target Course, recognized/released CH, type, and approval date; no synthetic Enrollments.
- [ ] Transfers also show source institution/Course, original Grade, and normalized E-JUST Grade; Waivers show prior E-JUST Course/Grade; Exemptions show qualification-based reason.
- [ ] Revoked decisions and under-review requests are excluded from the current effective-decision section. Evidence attachments, private document identifiers/URLs, and reviewer notes are excluded.
- [ ] EN/AR screens and print layouts display the full decision section and summaries using owner Public DTOs and generated client types, preserving ticket 02's scopes.
- [ ] HTTP tests cover each decision type, revocation, source detail, Exemption-plus-earned-credit accounting, category totals, null values, and absence of private evidence; focused frontend tests cover meaningful rendering behavior.
- [ ] API artifacts and bilingual messages are synchronized; relevant tests and affected gates pass, with concrete reasons for any gate not run.
