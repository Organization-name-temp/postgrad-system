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
    'program' => [
        'not_found' => 'The selected program could not be found.',
        'degree_mismatch' => 'The selected program does not match the degree you are applying for.',
        'not_qualified' => 'Your previous degree does not qualify you for this program.',
    ],
    'finalize' => [
        'not_ready' => 'This application is not ready to be finalized.',
        'no_program' => 'Choose a program before finalizing your application.',
        'missing_documents' => 'Upload every required document before finalizing your application.',
    ],
    'resubmit' => [
        'not_returned' => 'This application has not been returned for revision.',
    ],
    'verification' => [
        'not_pending' => 'This application is not awaiting email verification.',
    ],
    // The hand-rolled status machine's 409 (ticket 07): the requested move is not a
    // legal transition from the application's current state.
    'transition' => [
        'illegal' => 'This application cannot move from :from to :to.',
    ],
];
