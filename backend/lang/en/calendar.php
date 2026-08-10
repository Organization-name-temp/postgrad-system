<?php

// Calendar domain messages. Localised per request by the Accept-Language middleware
// (en here, lang/ar/calendar.php for Arabic).
//
// The `ordering` keys mirror `SemesterCalendar::orderingViolations()` one-for-one: that pure
// authority returns `ordering.<field>` for each rule broken, keyed to the field an administrator
// would move to fix it, and the controller renders it from here — so the 422 an admin reads is
// in their own language and the rule itself still lives in exactly one place (ADR-0018).
return [

    'ordering' => [
        'registration_opens_at' => 'Registration must open before it closes and before the semester starts.',
        'registration_closes_at' => 'Registration must close on or before the add/drop deadline.',
        'add_drop_ends_at' => 'The add/drop period must end before the late-add deadline.',
        'late_add_ends_at' => 'The late-add period must end before the withdrawal deadline.',
        'withdrawal_deadline_at' => 'The withdrawal deadline must be on or before the semester ends.',
        // Phase 4 ticket 09: grades are submitted for a term that has already finished.
        'grade_submission_deadline_at' => 'The grade-submission deadline must be on or after the semester ends.',
        'ends_at' => 'The semester must end after it starts.',
    ],

];
