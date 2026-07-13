<?php

// Catalog domain validation lines. Localised per request by the Accept-Language
// middleware (en here, lang/ar/catalog.php for Arabic). Laravel's built-in rules
// (required, unique, exists, enum) cover the rest; this file holds only the custom
// domain messages the Catalog controllers raise.
return [
    'credit_hours_unbalanced' => 'The lecture, tutorial and lab hours must add up to the total.',
    'curriculum_duplicate' => 'This course is already in that category for this program.',
];
