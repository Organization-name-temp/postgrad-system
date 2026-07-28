<?php

// Identity domain lines. Localised per request by the Accept-Language middleware
// (en here, lang/ar/identity.php for Arabic). Only the custom domain messages the
// Identity controllers raise directly live here; Laravel's built-in validation
// rules keep their bilingual messages in lang/{locale}/validation.php.
return [
    // The admin assignment surfaces: Dean↔Faculty (ticket 08) and Department
    // Head↔Department (phase 4, ticket 02).
    'assignment' => [
        'not_a_dean' => 'The selected user is not a Dean.',
        'duplicate' => 'This Dean is already assigned to that faculty.',
        'not_a_head' => 'The selected user is not a Department Head.',
        'duplicate_head' => 'This Department Head is already assigned to that department.',
        // The NULL-Department scope needs its own wording: "assigned to that
        // department" would name a Department the administrator never picked.
        'duplicate_university_wide' => 'This Department Head already covers the university-wide Preparatory courses.',
    ],
];
