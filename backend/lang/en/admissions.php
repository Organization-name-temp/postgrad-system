<?php

// Admissions domain lines. Localised per request by the Accept-Language
// middleware (en here, lang/ar/admissions.php for Arabic). Laravel's built-in
// rules (required, unique, email, in) cover field validation; this file holds
// only the custom domain messages the Admissions controllers raise.
return [
    'registration' => [
        'closed' => 'Admissions are currently closed.',
    ],
];
