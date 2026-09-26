<?php

use Symfony\Component\Yaml\Yaml;

it('publishes the complete nullable manual Exemption resource contract', function () {
    $spec = Yaml::parseFile(dirname(__DIR__, 3).'/openapi.yaml');
    $effectiveDecision = $spec['paths']['/api/v1/credit-decisions/exemptions']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['properties'];
    $path = $spec['paths']['/api/v1/credit-decisions/exemption-requests'];
    $item = $path['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['properties'];
    $createEvidence = $path['post']['requestBody']['content']['application/json']['schema']['properties']['evidence']['properties'];

    expect($effectiveDecision['admission_application_id']['type'])->toBe('integer')
        ->and($effectiveDecision['admission_application_id']['nullable'])->toBeTrue()
        ->and($effectiveDecision['manual_request_id']['type'])->toBe('integer')
        ->and($effectiveDecision['manual_request_id']['nullable'])->toBeTrue()
        ->and($createEvidence['document_ids']['type'])->toBe('array')
        ->and($createEvidence['document_ids']['items']['type'])->toBe('integer')
        ->and($item['evidence']['properties']['document_ids']['items']['type'])->toBe('integer')
        ->and($item['transitions']['items']['properties']['evidence_snapshot']['properties']['document_ids']['items']['type'])->toBe('integer')
        ->and($item['student_note']['nullable'])->toBeTrue()
        ->and($item['submitted_at']['nullable'])->toBeTrue()
        ->and($item['transitions']['items']['properties']['from_status']['nullable'])->toBeTrue()
        ->and($item['transitions']['items']['properties']['reason']['nullable'])->toBeTrue()
        ->and($item['decision']['nullable'])->toBeTrue()
        ->and($item['decision']['properties']['admission_application_id']['type'])->toBe('integer')
        ->and($item['decision']['properties']['admission_application_id']['nullable'])->toBeTrue();

    $resourceEndpoints = [
        ['post', '/api/v1/credit-decisions/exemption-requests', 201],
        ['get', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200],
        ['patch', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200],
        ['delete', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/submit', 200],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/advisor-decision', 200],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/department-head-decision', 200],
    ];
    $resourceFields = [
        'id', 'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
        'preparatory_requirement_role', 'credit_hours', 'evidence', 'student_note', 'status',
        'revision', 'editable', 'terminal', 'submitted_at', 'created_at', 'updated_at',
        'transitions', 'decision',
    ];
    $evidenceFields = [
        'english_test', 'english_score', 'previous_degree', 'is_ejust_graduate',
        'previous_ejust_program_id', 'document_ids',
    ];
    $transitionFields = [
        'id', 'from_status', 'to_status', 'action', 'actor_user_id', 'reason', 'revision',
        'evidence_snapshot', 'current_revision', 'created_at',
    ];

    expect(array_keys($item))->toEqualCanonicalizing($resourceFields)
        ->and(array_keys($item['evidence']['properties']))->toEqualCanonicalizing($evidenceFields)
        ->and(array_keys($item['transitions']['items']['properties']))->toEqualCanonicalizing($transitionFields)
        ->and(array_keys($item['transitions']['items']['properties']['evidence_snapshot']['properties']))->toEqualCanonicalizing($evidenceFields);

    foreach (['advisor-queue', 'department-head-queue'] as $queue) {
        $queueItem = $spec['paths']["/api/v1/credit-decisions/exemption-requests/{$queue}"]['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['properties'];

        expect(array_keys($queueItem))->toEqualCanonicalizing([...$resourceFields, 'student'])
            ->and(array_keys($queueItem['evidence']['properties']))->toEqualCanonicalizing($evidenceFields)
            ->and($queueItem['evidence']['properties']['document_ids']['items']['type'])->toBe('integer')
            ->and(array_keys($queueItem['transitions']['items']['properties']))->toEqualCanonicalizing($transitionFields)
            ->and(array_keys($queueItem['transitions']['items']['properties']['evidence_snapshot']['properties']))->toEqualCanonicalizing($evidenceFields)
            ->and($queueItem['transitions']['items']['properties']['evidence_snapshot']['properties']['document_ids']['items']['type'])->toBe('integer')
            ->and($queueItem['student_note']['nullable'])->toBeTrue()
            ->and($queueItem['submitted_at']['nullable'])->toBeTrue()
            ->and($queueItem['transitions']['items']['properties']['from_status']['nullable'])->toBeTrue()
            ->and($queueItem['transitions']['items']['properties']['reason']['nullable'])->toBeTrue()
            ->and($queueItem['decision']['type'])->toBe('object')
            ->and($queueItem['decision']['nullable'])->toBeTrue();
    }

    foreach ($resourceEndpoints as [$method, $resourcePath, $status]) {
        $properties = $spec['paths'][$resourcePath][$method]['responses'][$status]['content']['application/json']['schema']['properties']['data']['properties'];
        $transitionProperties = $properties['transitions']['items']['properties'];

        expect(array_keys($properties))->toEqualCanonicalizing($resourceFields)
            ->and(array_keys($properties['evidence']['properties']))->toEqualCanonicalizing($evidenceFields)
            ->and($properties['evidence']['properties']['document_ids']['items']['type'])->toBe('integer')
            ->and($properties['student_note']['nullable'])->toBeTrue()
            ->and($properties['submitted_at']['nullable'])->toBeTrue()
            ->and(array_keys($transitionProperties))->toEqualCanonicalizing($transitionFields)
            ->and(array_keys($transitionProperties['evidence_snapshot']['properties']))->toEqualCanonicalizing($evidenceFields)
            ->and($transitionProperties['evidence_snapshot']['properties']['document_ids']['items']['type'])->toBe('integer')
            ->and($transitionProperties['from_status']['nullable'])->toBeTrue()
            ->and($transitionProperties['reason']['nullable'])->toBeTrue()
            ->and($properties['decision']['type'])->toBe('object')
            ->and($properties['decision']['nullable'])->toBeTrue();
    }

    $stateExamples = [
        ['post', '/api/v1/credit-decisions/exemption-requests', 201, 'Draft'],
        ['get', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200, 'Approved'],
        ['patch', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200, 'Returned'],
        ['delete', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}', 200, 'Abandoned'],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/submit', 200, 'PendingAdvisor'],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/advisor-decision', 200, 'PendingDepartmentHead'],
        ['post', '/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/department-head-decision', 200, 'Approved'],
    ];

    foreach ($stateExamples as [$method, $resourcePath, $status, $expectedState]) {
        $properties = $spec['paths'][$resourcePath][$method]['responses'][$status]['content']['application/json']['schema']['properties']['data']['properties'];

        expect($properties['status']['example'])->toBe($expectedState);
        if ($expectedState !== 'Approved') {
            expect($properties['decision']['example'])->toBeNull();
        }
    }

    foreach ([
        'advisor-queue' => 'PendingAdvisor',
        'department-head-queue' => 'PendingDepartmentHead',
    ] as $queue => $expectedState) {
        $schema = $spec['paths']["/api/v1/credit-decisions/exemption-requests/{$queue}"]['get']['responses'][200]['content']['application/json']['schema']['properties'];

        expect($schema['data']['items']['properties']['status']['example'])->toBe($expectedState)
            ->and($schema['data']['items']['properties']['decision']['example'])->toBeNull()
            ->and($schema['meta']['properties']['path']['example'])->toEndWith('/'.$queue);
    }

    $approvedDecision = $spec['paths']['/api/v1/credit-decisions/exemption-requests/{exemption_request_id}/department-head-decision']['post']['responses'][200]['content']['application/json']['schema']['properties']['data']['properties']['decision']['properties'];
    expect($approvedDecision['reason_codes']['example'])->toBe(['english_proficiency'])
        ->and($approvedDecision['admission_application_id']['example'])->toBeNull();

    foreach ([
        ['/api/v1/credit-decisions/exemption-requests', ['target_course_id']],
        ['/api/v1/credit-decisions/exemption-requests/export', ['target_course_id']],
        ['/api/v1/credit-decisions/exemption-requests/advisor-queue', ['target_course_id', 'student_id']],
        ['/api/v1/credit-decisions/exemption-requests/advisor-queue/export', ['target_course_id', 'student_id']],
        ['/api/v1/credit-decisions/exemption-requests/department-head-queue', ['target_course_id', 'student_id']],
        ['/api/v1/credit-decisions/exemption-requests/department-head-queue/export', ['target_course_id', 'student_id']],
    ] as [$collectionPath, $integerFilters]) {
        $parameters = collect($spec['paths'][$collectionPath]['get']['parameters'])->keyBy('name');
        foreach ($integerFilters as $filter) {
            expect($parameters[$filter]['schema']['type'])->toBe('string')
                ->and($parameters[$filter]['example'])->toBeString()->toContain(',');
        }
    }

    foreach ([
        '/api/v1/credit-decisions/exemption-requests/export' => 'exportMyManualExemptions',
        '/api/v1/credit-decisions/exemption-requests/advisor-queue/export' => 'exportAdvisorExemptionQueue',
        '/api/v1/credit-decisions/exemption-requests/department-head-queue/export' => 'exportDepartmentHeadExemptionQueue',
    ] as $exportPath => $operationId) {
        expect($spec['paths'][$exportPath]['get']['operationId'])->toBe($operationId);
    }
});
