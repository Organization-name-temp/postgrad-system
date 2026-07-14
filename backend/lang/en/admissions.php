<?php

// Admissions domain lines. Localised per request by the Accept-Language
// middleware (en here, lang/ar/admissions.php for Arabic). Laravel's built-in
// rules (required, unique, email, in) cover field validation — their bilingual
// messages live in lang/{locale}/validation.php; this file holds only the custom
// domain messages the Admissions controllers raise directly.
return [
    'registration' => [
        'closed' => 'Admissions are currently closed.',
    ],
    'application' => [
        'not_editable' => 'This application can no longer be edited.',
        'already_submitted' => 'Step 1 has already been submitted for this application.',
    ],
    'documents' => [
        'locked' => 'Documents can no longer be changed for this application.',
        'too_many' => 'You can upload at most :max document(s) of this type.',
    ],
    'verification' => [
        'not_pending' => 'This application is not awaiting email verification.',
    ],
];
