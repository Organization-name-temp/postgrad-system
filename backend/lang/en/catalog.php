<?php

// Catalog domain validation lines. Localised per request by the Accept-Language
// middleware (en here, lang/ar/catalog.php for Arabic). Laravel's built-in rules
// (required, unique, exists, enum) cover the rest; this file holds only the custom
// domain messages the Catalog controllers raise.
return [
    'reference_columns' => [
        'id' => 'Reference',
        'code' => 'Code',
        'name_en' => 'English name',
        'name_ar' => 'Arabic name',
        'is_active' => 'Active state',
        'department_id' => 'Department reference',
        'faculty_id' => 'Faculty reference',
        'degree_level' => 'Degree',
        'prep_required_ch' => 'Preparatory required CH',
        'core_required_ch' => 'Core required CH',
        'elective_required_ch' => 'Elective required CH',
        'total_required_ch' => 'Total required CH',
    ],
    'export_columns' => [
        'id' => 'Course reference',
        'department_id' => 'Department reference',
        'code' => 'Course code',
        'name_en' => 'English name',
        'name_ar' => 'Arabic name',
        'level' => 'Level',
        'research_type' => 'Research Type',
        'lecture_ch' => 'Lecture CH',
        'tutorial_ch' => 'Tutorial CH',
        'lab_ch' => 'Lab CH',
        'total_ch' => 'Total CH',
        'is_active' => 'Active state',
    ],
    'export_values' => [
        'MSc' => 'Master of Science',
        'PhD' => 'Doctor of Philosophy',
        'PBL' => 'Problem-based learning',
        'Seminar' => 'Seminar',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'credit_hours_unbalanced' => 'The lecture, tutorial and lab hours must add up to the total.',
    'curriculum_duplicate' => 'This course is already in that category for this program.',
    'preparatory_role_duplicate' => 'This Preparatory Requirement Role is already assigned in this Program.',
    'preparatory_role_required' => 'Every Preparatory Curriculum Entry requires a Preparatory Requirement Role.',
    'key_skills_must_be_departmental' => 'Key Skills must be assigned to a Course owned by this Program’s Department.',
    'preparatory_role_only_for_preparatory' => 'Only Preparatory Curriculum Entries may have a Preparatory Requirement Role.',
];
