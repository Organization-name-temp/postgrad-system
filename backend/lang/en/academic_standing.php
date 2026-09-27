<?php

return [
    'request_closed' => 'System rejected by termination case #:case.',
    'evidence_changed' => 'Termination evidence changed; reload the case.',
    'lifecycle_inactive' => 'The Student lifecycle is no longer Active.',
    'curriculum_missing' => 'The student has no bound curriculum.',
    'student_required' => 'You do not have a Student record.',
    'student_missing' => 'No such Student.',
    'curriculum_unavailable' => 'The bound Curriculum Version is unavailable.',
    'report' => [
        'headers' => [
            'student_id' => 'Student ID', 'university_id' => 'University ID',
            'name_en' => 'Student name (English)', 'name_ar' => 'Student name (Arabic)',
            'program_code' => 'Program code', 'program' => 'Program', 'degree_level' => 'Degree',
            'lifecycle' => 'Lifecycle', 'cgpa' => 'CGPA', 'standing' => 'Academic Standing',
            'explanation' => 'Explanation', 'unavailable' => 'Unavailable data',
        ],
        'standings' => [
            'TerminationPending' => 'Termination pending', 'NotYetAssessed' => 'Not yet assessed',
            'AcademicProbation' => 'Academic Probation', 'AcademicWarning' => 'Academic Warning',
            'GoodStanding' => 'Good Standing',
        ],
        'explanations' => [
            'termination_pending' => 'An actionable termination Case is open.',
            'no_finalized_cgpa_grade' => 'No finalized CGPA-counting Grade yet.',
            'cgpa_below_minimum' => 'CGPA is below the minimum.',
            'effective_grade_below_c_plus' => 'An effective Grade is below C+.',
            'requirements_met' => 'Current Standing requirements are met.',
        ],
        'lifecycle' => ['Active' => 'Active', 'Terminated' => 'Terminated'],
        'degrees' => ['MSc' => 'MSc', 'PhD' => 'PhD'],
        'unavailable' => ['program' => 'Program unavailable', 'student_name' => 'Student name unavailable'],
    ],
    'graduation_report' => [
        'headers' => ['readiness' => 'Advisory readiness', 'remaining_ch' => 'Remaining CH', 'cgpa' => 'CGPA', 'approaching' => 'Approaching Graduation (no proposal)', 'blockers' => 'Blockers (overlapping)', 'advisory' => 'Advisory status', 'unavailable' => 'Unavailable data'],
        'groups' => ['ready' => 'Academically ready (advisory)', 'not_ready' => 'Not academically ready', 'unavailable' => 'Readiness unavailable'],
        'boolean' => ['yes' => 'Yes', 'no' => 'No'],
        'blockers' => ['RemainingRequirements' => 'Remaining curriculum requirements', 'CgpaUnavailable' => 'CGPA unavailable', 'CgpaBelowMinimum' => 'CGPA below minimum', 'TerminationPending' => 'Actionable termination Case pending'],
        'advisory' => ['Active' => 'Advisory academic facts; not formal graduation approval', 'Terminated' => 'Historical advisory facts; not currently eligible for graduation'],
    ],
    'termination_report' => [
        'headers' => ['state' => 'Workflow stage / outcome', 'created_at' => 'Case created (UTC)', 'advisor_reviewed_at' => 'Advisor assessed (UTC)', 'department_head_reviewed_at' => 'Department Head reviewed (UTC)', 'council_decided_at' => 'Council decided (UTC)', 'closed_at' => 'Case closed (UTC)', 'unavailable' => 'Unavailable data'],
        'states' => ['AwaitingAdvisor' => 'Flagged: awaiting Advisor assessment', 'AwaitingDepartmentHead' => 'Advisor assessed: awaiting Department Head review', 'AwaitingCouncil' => 'Recommended: awaiting Council decision', 'Dismissed' => 'Dismissed', 'Terminated' => 'Terminated', 'TriggerCleared' => 'Trigger cleared', 'EvidenceChanged' => 'Evidence changed / replaced'],
    ],
    'exports' => [
        'headers' => [
            'case_id' => 'Case ID',
            'student_id' => 'Student ID',
            'state' => 'State',
            'advisor_id' => 'Advisor ID',
            'advisor_recommendation' => 'Advisor recommendation',
            'department_head_id' => 'Department Head ID',
            'council_id' => 'Council ID',
            'closed_reason' => 'Closed reason',
            'closed_at' => 'Closed at',
        ],
        'states' => [
            'AwaitingAdvisor' => 'Awaiting Advisor',
            'AwaitingDepartmentHead' => 'Awaiting Department Head',
            'AwaitingCouncil' => 'Awaiting Council',
            'Dismissed' => 'Dismissed',
            'Terminated' => 'Terminated',
            'TriggerCleared' => 'Trigger cleared',
            'EvidenceChanged' => 'Evidence changed',
        ],
        'recommendations' => ['continue' => 'Continue', 'dismiss' => 'Dismiss'],
    ],
];
