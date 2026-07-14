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

];
