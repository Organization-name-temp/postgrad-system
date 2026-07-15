<?php

// Admissions tunables (Phase 2). Config-driven so the intake rules can be adjusted
// without code changes; the defaults encode the SRS/spec values.
return [

    // Email verification (ticket 04). The signed verification link expires after
    // this many hours (SRS FR-ADM-002); no mail is sent — the URL is returned in
    // the API response and logged (ADR-0005). Resending is throttled to a small
    // number of attempts per rolling 24h window (BR-72) so a missed link can be
    // recovered without becoming an abuse vector.
    'email_verification_hours' => (int) env('ADMISSIONS_EMAIL_VERIFICATION_HOURS', 24),
    'verification_resend_max_per_day' => (int) env('ADMISSIONS_VERIFICATION_RESEND_MAX_PER_DAY', 5),

    // Review SLAs (ticket 11). How many **business days** (Egyptian Fri/Sat weekend
    // excluded, no holiday calendar) an application may wait in a review stage before
    // it is considered overdue. Aging is derived on demand from the timeline entry
    // into the stage — never stored — and only the manual `admissions:flag-overdue`
    // command acts on it, by notifying the relevant reviewer; nothing escalates
    // automatically (spec §SLAs / §Out of Scope).
    'sa_screening_sla_days' => (int) env('ADMISSIONS_SA_SCREENING_SLA_DAYS', 5),
    'dean_review_sla_days' => (int) env('ADMISSIONS_DEAN_REVIEW_SLA_DAYS', 5),

    // Interview reminders (ticket 11). The manual `admissions:send-interview-reminders`
    // command nudges both the applicant and the assigned interviewer about every
    // upcoming interview whose start is within this many hours — run it two days out
    // to catch the ~48h reminder and again a day out for the ~24h one. In-app only
    // (no mail); it changes no status.
    'interview_reminder_window_hours' => (int) env('ADMISSIONS_INTERVIEW_REMINDER_WINDOW_HOURS', 48),

];
