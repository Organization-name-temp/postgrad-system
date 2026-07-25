<?php

// Grading domain messages. Localised per request by the Accept-Language middleware
// (en here, lang/ar/grading.php for Arabic). Laravel's built-in rules cover the rest;
// this file holds only the domain messages the Grading controllers raise.
return [

    'mark' => [
        // The 0–100 range (ADR-0021's scale is total over it and nothing outside is a grade).
        // Raised against the `mark` field, so the 422's errors object names it.
        'out_of_range' => 'A mark must be a percentage between 0 and 100.',
        'too_precise' => 'A mark may have at most two decimal places.',
    ],

    'offering' => [
        'missing' => 'That course offering no longer exists.',
        // Authority over a Grade Sheet is the Offering assignment, not the grades.enter
        // permission the route already checked (ADR-0023).
        'not_yours' => 'You are not assigned to teach this offering.',
    ],

    'line' => [
        'not_gradable' => 'That enrollment is not a gradable line on this grade sheet.',
    ],

    'sheet' => [
        // A Submitted sheet is under review and a Finalized one is closed forever (ADR-0023).
        'not_editable' => 'This grade sheet is no longer open for marks.',
    ],

];
