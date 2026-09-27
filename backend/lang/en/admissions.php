<?php

// Admissions domain lines. Localised per request by the Accept-Language
// middleware (en here, lang/ar/admissions.php for Arabic). Laravel's built-in
// rules (required, unique, email, in) cover field validation — their bilingual
// messages live in lang/{locale}/validation.php; this file holds only the custom
// domain messages the Admissions controllers raise directly.
return [
    'export_columns' => ['id' => 'Application reference', 'name_en' => 'English name', 'name_ar' => 'Arabic name', 'national_id' => 'National ID / passport', 'degree' => 'Degree', 'program' => 'Program', 'status' => 'Status', 'age_days' => 'Days in review', 'date_of_birth' => 'Date of birth', 'nationality' => 'Nationality', 'phone' => 'Phone', 'address' => 'Address', 'finalized_at' => 'Finalized at'],
    'statuses' => ['draft' => 'Draft', 'submitted' => 'Submitted', 'email_verified' => 'Email verified', 'under_sa_review' => 'Under Student Affairs review', 'sa_approved' => 'Student Affairs approved', 'returned_for_revision' => 'Returned for revision', 'under_dean_review' => 'Under Dean review', 'interview_scheduled' => 'Interview scheduled', 'interview_completed' => 'Interview completed', 'accepted' => 'Accepted', 'conditionally_accepted' => 'Conditionally accepted', 'waitlisted' => 'Waitlisted', 'rejected' => 'Rejected'],
    // Locale-aware separator for lists built in a message (e.g. the missing-documents
    // enumeration) — Arabic uses its own comma, so it must not be hardcoded.
    'list_separator' => ', ',
    'registration' => [
        'closed' => 'Admissions are currently closed.',
    ],
    'application' => [
        'not_editable' => 'This application can no longer be edited.',
        'already_submitted' => 'Step 1 has already been submitted for this application.',
        // Raised by the national_id format rule when the identifier does not match the
        // format of the chosen Identification Type (14 digits for a National ID,
        // 6–9 alphanumeric for a Passport).
        'invalid_identifier' => 'The identifier does not match the selected identification type.',
        // Raised by the GPA-vs-scale rule when a GPA/CGPA exceeds the chosen scale's
        // ceiling (e.g. 90 on a 4.0 scale).
        'gpa_above_scale' => 'The GPA cannot exceed the chosen scale’s maximum of :ceiling.',
        // Raised by the english_score rule when the score falls outside the selected
        // test's official range (e.g. an IELTS of 250).
        'english_score_out_of_range' => 'The :test score must be between :min and :max.',
        // Raised when a score is supplied for the "None" test, which carries no score.
        'english_score_not_allowed' => 'The selected test does not take a score.',
        'invalid_previous_ejust_program' => 'Choose a valid previous E-JUST MSc Program.',
    ],
    'documents' => [
        'locked' => 'Documents can no longer be changed for this application.',
        'too_many' => 'You can upload at most :max document(s) of this type.',
        // Human-readable DocumentType names, used to enumerate what is still missing
        // at finalize (keyed by the enum value).
        'types' => [
            'graduation_certificate' => 'Graduation certificate',
            'transcript' => 'Academic transcript',
            'national_id' => 'National ID / passport',
            'photo' => 'Personal photo',
            'english_certificate' => 'English proficiency certificate',
            'msc_thesis' => 'MSc thesis',
            'cv' => 'CV',
            'recommendation_letter' => 'Recommendation letter',
            'additional_certificate' => 'Additional certificate',
        ],
    ],
    'program' => [
        'not_found' => 'The selected program could not be found.',
        'degree_mismatch' => 'The selected program does not match the degree you are applying for.',
        'not_qualified' => 'Your previous degree does not qualify you for this program.',
    ],
    'finalize' => [
        'not_ready' => 'This application is not ready to be finalized.',
        'no_program' => 'Choose a program before finalizing your application.',
        'missing_documents' => 'Upload the following required document(s) before finalizing: :documents.',
    ],
    'resubmit' => [
        'not_returned' => 'This application has not been returned for revision.',
    ],
    'verification' => [
        'not_pending' => 'This application is not awaiting email verification.',
    ],
    // Interview slots + scheduling + evaluation (ticket 09): the domain conflicts the
    // interview controllers raise directly (409), plus the not-a-faculty-interviewer
    // validation message (422).
    'interview' => [
        'not_faculty' => 'The selected interviewer is not a faculty member.',
        'slot_booked' => 'This slot has an interview booked and cannot be changed.',
        'not_awaiting_scheduling' => 'This application is not awaiting interview scheduling.',
        'already_scheduled' => 'This application already has an interview scheduled.',
        'not_pending' => 'This interview is not awaiting confirmation.',
        'reschedule_exhausted' => 'You have already rescheduled this interview once.',
        'already_completed' => 'This interview has already been completed.',
    ],
    // Final decision + acceptance mint (ticket 10): the validation the decision
    // controller raises directly (422) when the advisor for an acceptance cannot be
    // resolved, or the application has no program to admit into.
    'decision' => [
        'no_program' => 'This application has no program to admit the applicant into.',
        'no_curriculum_version' => 'The selected program has no Curriculum Version available for admission.',
        'advisor_not_r03' => 'The selected advisor must be an academic advisor.',
        'no_advisor' => 'No academic advisor is available to assign.',
    ],
    // The hand-rolled status machine's 409 (ticket 07): the requested move is not a
    // legal transition from the application's current state.
    'transition' => [
        'illegal' => 'This application cannot move from :from to :to.',
    ],
];
