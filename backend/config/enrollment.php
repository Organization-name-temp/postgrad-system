<?php

// Enrollment tunables (Phase 3). Config-driven so the registration load rules can be
// adjusted without code changes (the config/admissions.php pattern); the defaults
// encode the SRS/spec values.
return [

    // Credit-hour load per registration (FR-REG-001, spec §Rules engines). A submitted
    // cart must total at least the minimum and at most the maximum credit hours,
    // counting every Regular + Audit line. The 6-CH floor is waived for a Student's
    // first semester (target semester == their enrollment term); Phase 5 tightens that
    // to exempted-only and adds the approaching-graduation exception. Read by the pure
    // CreditHoursValidator, which the cart submission and (ticket 06) the advisor
    // decision both validate against.
    'min_credit_hours' => (int) env('ENROLLMENT_MIN_CREDIT_HOURS', 6),
    'max_credit_hours' => (int) env('ENROLLMENT_MAX_CREDIT_HOURS', 12),

];
