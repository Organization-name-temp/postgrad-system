<?php

namespace Modules\Enrollment\Internal\Http\Documentation;

/** Authoritative, state-valid Scribe examples for the manual Exemption resource. */
final class ManualExemptionResponseExamples
{
    private const EVIDENCE = [
        'english_test' => 'IELTS',
        'english_score' => 7,
        'previous_degree' => 'MSc',
        'is_ejust_graduate' => true,
        'previous_ejust_program_id' => 2,
        'document_ids' => [41, 42],
    ];

    private const BASE = [
        'id' => 1,
        'student_id' => 10,
        'curriculum_version_id' => 3,
        'target_course_id' => 5,
        'target_course_code' => 'PRE401',
        'preparatory_requirement_role' => 'EnglishLanguage',
        'credit_hours' => 1,
        'evidence' => self::EVIDENCE,
        'student_note' => 'Corrected report',
        'created_at' => '2026-09-04T09:00:00+00:00',
        'updated_at' => '2026-09-04T10:00:00+00:00',
    ];

    private const DRAFT_CREATED = [
        'id' => 1,
        'from_status' => null,
        'to_status' => 'Draft',
        'action' => 'draft_created',
        'actor_user_id' => 12,
        'reason' => null,
        'revision' => 1,
        'evidence_snapshot' => self::EVIDENCE,
        'current_revision' => true,
        'created_at' => '2026-09-04T09:00:00+00:00',
    ];

    private const SUBMITTED = [
        ...self::DRAFT_CREATED,
        'id' => 2,
        'from_status' => 'Draft',
        'to_status' => 'PendingAdvisor',
        'action' => 'submitted',
        'created_at' => '2026-09-04T09:30:00+00:00',
    ];

    private const RECOMMENDED = [
        ...self::DRAFT_CREATED,
        'id' => 3,
        'from_status' => 'PendingAdvisor',
        'to_status' => 'PendingDepartmentHead',
        'action' => 'recommended_by_advisor',
        'actor_user_id' => 14,
        'created_at' => '2026-09-04T09:45:00+00:00',
    ];

    private const DRAFT_DATA = [
        ...self::BASE,
        'status' => 'Draft',
        'revision' => 1,
        'editable' => true,
        'terminal' => false,
        'submitted_at' => null,
        'transitions' => [self::DRAFT_CREATED],
        'decision' => null,
    ];

    private const RETURNED_DATA = [
        ...self::BASE,
        'status' => 'Returned',
        'revision' => 2,
        'editable' => true,
        'terminal' => false,
        'submitted_at' => '2026-09-04T09:30:00+00:00',
        'transitions' => [[
            ...self::DRAFT_CREATED,
            'current_revision' => false,
        ], [
            ...self::SUBMITTED,
            'current_revision' => false,
        ], [
            ...self::DRAFT_CREATED,
            'id' => 3,
            'from_status' => 'PendingAdvisor',
            'to_status' => 'Returned',
            'action' => 'returned_by_advisor',
            'actor_user_id' => 14,
            'reason' => 'Upload a readable score report.',
            'current_revision' => false,
            'created_at' => '2026-09-04T09:45:00+00:00',
        ]],
        'decision' => null,
    ];

    private const ABANDONED_DATA = [
        ...self::BASE,
        'status' => 'Abandoned',
        'revision' => 1,
        'editable' => false,
        'terminal' => true,
        'submitted_at' => null,
        'transitions' => [self::DRAFT_CREATED, [
            ...self::DRAFT_CREATED,
            'id' => 2,
            'from_status' => 'Draft',
            'to_status' => 'Abandoned',
            'action' => 'abandoned',
            'created_at' => '2026-09-04T09:15:00+00:00',
        ]],
        'decision' => null,
    ];

    private const PENDING_ADVISOR_DATA = [
        ...self::BASE,
        'status' => 'PendingAdvisor',
        'revision' => 1,
        'editable' => false,
        'terminal' => false,
        'submitted_at' => '2026-09-04T09:30:00+00:00',
        'transitions' => [self::DRAFT_CREATED, self::SUBMITTED],
        'decision' => null,
    ];

    private const PENDING_HEAD_DATA = [
        ...self::BASE,
        'status' => 'PendingDepartmentHead',
        'revision' => 1,
        'editable' => false,
        'terminal' => false,
        'submitted_at' => '2026-09-04T09:30:00+00:00',
        'transitions' => [self::DRAFT_CREATED, self::SUBMITTED, self::RECOMMENDED],
        'decision' => null,
    ];

    private const APPROVED_DATA = [
        ...self::BASE,
        'status' => 'Approved',
        'revision' => 1,
        'editable' => false,
        'terminal' => true,
        'submitted_at' => '2026-09-04T09:30:00+00:00',
        'transitions' => [self::DRAFT_CREATED, self::SUBMITTED, self::RECOMMENDED, [
            ...self::DRAFT_CREATED,
            'id' => 4,
            'from_status' => 'PendingDepartmentHead',
            'to_status' => 'Approved',
            'action' => 'approved_by_department_head',
            'actor_user_id' => 16,
            'created_at' => '2026-09-04T10:00:00+00:00',
        ]],
        'decision' => [
            'id' => 4,
            'student_id' => 10,
            'curriculum_version_id' => 3,
            'target_course_id' => 5,
            'target_course_code' => 'PRE401',
            'preparatory_requirement_role' => 'EnglishLanguage',
            'credit_hours' => 1,
            'source' => 'manual',
            'reason_codes' => ['english_proficiency'],
            'admission_application_id' => null,
            'manual_request_id' => 1,
            'approved_at' => '2026-09-04T10:00:00+00:00',
            'effective' => true,
            'revocation' => null,
        ],
    ];

    public const DRAFT = ['data' => self::DRAFT_DATA];

    public const RETURNED = ['data' => self::RETURNED_DATA];

    public const ABANDONED = ['data' => self::ABANDONED_DATA];

    public const PENDING_ADVISOR = ['data' => self::PENDING_ADVISOR_DATA];

    public const PENDING_HEAD = ['data' => self::PENDING_HEAD_DATA];

    public const APPROVED = ['data' => self::APPROVED_DATA];

    public const STUDENT_LIST = [
        'data' => [self::APPROVED_DATA],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => 'http://localhost/api/v1/credit-decisions/exemption-requests',
            'per_page' => 25,
            'to' => 1,
            'total' => 1,
        ],
    ];

    public const ADVISOR_QUEUE = [
        'data' => [[...self::PENDING_ADVISOR_DATA, 'student' => [
            'student_id' => 10,
            'university_id' => '2026-CSE-0001',
            'name' => 'Sara Student',
        ]]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => 'http://localhost/api/v1/credit-decisions/exemption-requests/advisor-queue',
            'per_page' => 25,
            'to' => 1,
            'total' => 1,
        ],
    ];

    public const DEPARTMENT_HEAD_QUEUE = [
        'data' => [[...self::PENDING_HEAD_DATA, 'student' => [
            'student_id' => 10,
            'university_id' => '2026-CSE-0001',
            'name' => 'Sara Student',
        ]]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => 'http://localhost/api/v1/credit-decisions/exemption-requests/department-head-queue',
            'per_page' => 25,
            'to' => 1,
            'total' => 1,
        ],
    ];
}
