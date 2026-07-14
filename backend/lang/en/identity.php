<?php

// Identity domain lines. Localised per request by the Accept-Language middleware
// (en here, lang/ar/identity.php for Arabic). Only the custom domain messages the
// Identity controllers raise directly live here; Laravel's built-in validation
// rules keep their bilingual messages in lang/{locale}/validation.php.
return [
    // Dean↔Faculty assignment admin surface (ticket 08).
    'assignment' => [
        'not_a_dean' => 'The selected user is not a Dean.',
        'duplicate' => 'This Dean is already assigned to that faculty.',
    ],
];
