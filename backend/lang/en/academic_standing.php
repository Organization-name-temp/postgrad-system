<?php

return [
    'request_closed' => 'System rejected by termination case #:case.',
    'evidence_changed' => 'Termination evidence changed; reload the case.',
    'lifecycle_inactive' => 'The Student lifecycle is no longer Active.',
    'curriculum_missing' => 'The student has no bound curriculum.',
    'student_required' => 'You do not have a Student record.',
    'student_missing' => 'No such Student.',
    'curriculum_unavailable' => 'The bound Curriculum Version is unavailable.',
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
