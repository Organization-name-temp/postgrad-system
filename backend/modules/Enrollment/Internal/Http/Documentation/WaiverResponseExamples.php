<?php

namespace Modules\Enrollment\Internal\Http\Documentation;

final class WaiverResponseExamples
{
    private const APPROVED_DATA = [
        'id' => 1, 'student_id' => 10, 'curriculum_version_id' => 3,
        'target_course_id' => 5, 'target_course_code' => 'CSE511', 'credit_hours' => 3,
        'prior_course_id' => 8, 'prior_course_code' => 'CSE411', 'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript', 'prior_enrollment_id' => null,
        'prior_letter_grade' => 'C+',
        'evidence' => [
            'source' => 'legacy_transcript', 'institution' => 'E-JUST', 'course_id' => 8,
            'letter_grade' => 'C+', 'official_transcript_document_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAW'],
        ],
        'student_note' => 'Prior E-JUST study evidence.', 'status' => 'Approved', 'revision' => 1,
        'editable' => false, 'terminal' => true, 'submitted_at' => '2026-09-04T09:05:00+00:00',
        'head_verification' => [
            'coverage_percentage' => 85.5, 'equivalency_note' => 'Learning outcomes align.',
            'verified_by' => 22, 'verified_at' => '2026-09-05T09:00:00+00:00',
        ], 'transitions' => [[
            'id' => 1, 'from_status' => null, 'to_status' => 'Draft', 'action' => 'draft_created',
            'actor_user_id' => 12, 'reason' => null, 'revision' => 1,
            'evidence_snapshot' => ['source' => 'legacy_transcript', 'institution' => 'E-JUST'],
            'verification_snapshot' => [
                'coverage_percentage' => 85.5, 'equivalency_note' => 'Learning outcomes align.',
                'verified_by' => 22, 'verified_at' => '2026-09-05T09:00:00+00:00',
            ], 'current_revision' => true,
            'created_at' => '2026-09-04T09:00:00+00:00',
        ]],
        'decision' => [
            'id' => 1, 'waiver_request_id' => 1, 'student_id' => 10,
            'curriculum_version_id' => 3, 'target_course_id' => 5,
            'target_course_code' => 'CSE511', 'credit_hours' => 3,
            'prior_course_id' => 8, 'prior_course_code' => 'CSE411',
            'prior_course_level' => 400, 'prior_result_source' => 'legacy_transcript',
            'prior_letter_grade' => 'C+', 'coverage_percentage' => 85.5,
            'verified_by' => 22, 'verified_at' => '2026-09-05T09:00:00+00:00',
            'approved_by' => 30, 'approved_at' => '2026-09-06T09:00:00+00:00',
        ], 'created_at' => '2026-09-04T09:00:00+00:00',
        'updated_at' => '2026-09-04T09:00:00+00:00',
    ];

    private const DRAFT_DATA = [
        ...self::APPROVED_DATA,
        'status' => 'Draft', 'editable' => true, 'terminal' => false,
        'submitted_at' => null, 'head_verification' => null, 'decision' => null,
    ];

    public const ITEM = ['data' => self::APPROVED_DATA];

    public const DRAFT = ['data' => self::DRAFT_DATA];

    public const LIST = [
        'data' => [self::APPROVED_DATA],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'path' => 'http://localhost/api/v1/credit-decisions/waiver-requests', 'per_page' => 25, 'to' => 1, 'total' => 1],
    ];

    public const REVIEW_LIST = [
        'data' => [[
            ...self::APPROVED_DATA,
            'student' => ['student_id' => 10, 'university_id' => '2026-CSE-0001', 'name' => 'Sara Student'],
            'evidence_documents' => [[
                'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                'type' => 'transcript',
                'original_filename' => 'transcript.pdf',
                'download_url' => '/api/v1/credit-decisions/waiver-requests/1/evidence-documents/01ARZ3NDEKTSV4RRFFQ69G5FAW',
            ]],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'path' => 'http://localhost/api/v1/credit-decisions/waiver-requests/advisor-queue', 'per_page' => 25, 'to' => 1, 'total' => 1],
    ];
}
