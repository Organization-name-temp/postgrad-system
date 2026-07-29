<?php

// Grading tunables (Phase 4, Appendix C). Config-driven so the bylaw figures can be adjusted
// without code changes (the config/enrollment.php pattern); the defaults encode the spec's
// values. The scale itself — the nine letters, their points and their bands — is NOT here: it
// lives in the `LetterGrade` enum as a static matrix (ADR-0021), a closed university bylaw set
// that belongs beside the code that reads it.
return [

    // Appendix C's `min_grade_per_course`: the C+ threshold (2.50 points) a for-credit Attempt
    // must reach to earn its credit hours. Read at a Sheet's finalize to derive the `earned`
    // bit pushed onto each Enrollment (ADR-0022, ticket 03), and again by ticket 07's
    // must-retake marker on a prep/core Course below it.
    'min_grade_per_course' => (float) env('GRADING_MIN_GRADE_PER_COURSE', 2.5),

    // SRS §4.7.3.1's Incomplete threshold: the minimum completion percentage an Instructor may
    // attest to when declaring an Incomplete (ticket 05). An attestation, not a measurement
    // (ADR-0019) — the API simply refuses a lower figure with a bilingual 422.
    'incomplete_min_completion' => (int) env('GRADING_INCOMPLETE_MIN_COMPLETION', 70),

    // SRS §4.7.3.4's reminder cadence, in days remaining before the derived deadline. Running
    // `grading:send-incomplete-reminders` daily produces exactly these four reminders, each
    // once — the manual command fires only on an exact match, never a range.
    'incomplete_reminder_offsets_days' => array_map(
        'intval',
        explode(',', (string) env('GRADING_INCOMPLETE_REMINDER_OFFSETS_DAYS', '28,14,7,1')),
    ),

];
