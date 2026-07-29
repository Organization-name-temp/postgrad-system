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

    'student' => [
        'missing' => 'You do not have a student record.',
    ],

    'sheet' => [
        // A Submitted sheet is under review and a Finalized one is closed forever (ADR-0023).
        'not_editable' => 'This grade sheet is no longer open for marks.',
        // Raised as a 409, naming both ends of the refused move (GradeSheet::transitionTo()).
        'illegal_transition' => 'A grade sheet cannot move from :from to :to.',
        'blank_lines_remain' => 'This grade sheet still has :count ungraded line(s) remaining.',
        'return_note_required' => 'A note is required when returning a grade sheet to its instructor.',
        'not_your_department' => 'This grade sheet does not belong to your department.',
    ],

];
