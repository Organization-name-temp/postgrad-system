<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Knuckles\Scribe\Attributes\ResponseField as ResponseFieldAttribute;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Http\Documentation\ManualExemptionResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\ExemptionRequestResource;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Support\ManualExemptionEvidencePresenter;
use Modules\Enrollment\Internal\Support\ManualExemptionWorkflow;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Support\Export\XlsxExporter;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Manual Exemptions */
class ExemptionRequestController
{
    /**
     * My bound Preparatory Exemption targets.
     *
     * @response 200 {"data": [{"target_course_id": 5, "target_course_code": "PRE401", "preparatory_requirement_role": "EnglishLanguage", "credit_hours": 1, "already_recognized": false}]}
     */
    public function targets(Request $request, StudentDirectory $students, CurriculumCatalog $curricula): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $existing = ExemptionDecision::query()->effective()
            ->where('student_id', $student->studentId)
            ->pluck('target_course_id')
            ->merge(TransferDecision::query()->effective()->where('student_id', $student->studentId)->pluck('target_course_id'))
            ->merge(WaiverDecision::query()->effective()->where('student_id', $student->studentId)->pluck('target_course_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();
        $rows = collect($curricula->entriesForVersion($student->curriculumVersionId))
            ->filter(fn ($entry): bool => $entry->category === CourseCategory::Preparatory && $entry->preparatoryRequirementRole !== null)
            ->map(fn ($entry): array => [
                'target_course_id' => $entry->courseId,
                'target_course_code' => $entry->courseCode,
                'preparatory_requirement_role' => $entry->preparatoryRequirementRole->value,
                'credit_hours' => $entry->totalCh,
                'already_recognized' => in_array($entry->courseId, $existing, true),
            ])->values()->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * My manual Exemption requests.
     *
     * @queryParam status string Comma-separated request statuses. Example: Draft,Returned
     * @queryParam target_course_id string Comma-separated positive Target Curriculum Course IDs. Example: 5,6
     * @queryParam q string Course-code search. Example: PRE
     * @queryParam sort string Comma-separated fields; prefix descending fields with -. Example: -updated_at
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Rows per page, capped at 100. Example: 25
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::STUDENT_LIST)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.effective', type: 'boolean', required: false)]
    #[ResponseFieldAttribute(name: 'data.decision.revocation', type: 'object', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.revocation.revoked_by_user_id', type: 'integer', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.revocation.reason', type: 'string', required: false)]
    #[ResponseFieldAttribute(name: 'data.decision.revocation.created_at', type: 'string', required: false)]
    public function index(Request $request, StudentDirectory $students): AnonymousResourceCollection
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $collection = $this->query($request);
        $query = ExemptionRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);

        return ExemptionRequestResource::collection($query->with(['transitions', 'decision.revocation'])->paginate($collection->perPage, page: $collection->page));
    }

    /**
     * Export my manual Exemptions.
     *
     * @queryParam status string Comma-separated request statuses. Example: Draft,Returned
     * @queryParam target_course_id string Comma-separated positive Target Curriculum Course IDs. Example: 5,6
     * @queryParam q string Course-code search. Example: PRE
     * @queryParam sort string Example: -updated_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, StudentDirectory $students, AuditLogger $audit, XlsxExporter $xlsx, ManualExemptionEvidencePresenter $evidencePresenter): BinaryFileResponse
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $student = $students->find($actorId);
        abort_if($student === null, 403);
        $collection = $this->query($request);
        $query = ExemptionRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);
        $rowCount = (clone $query)->count();
        $audit->record('exemption.requests.exported', $actorId, [
            'resource' => 'exemption_requests',
            'criteria' => $collection->auditCriteria(),
            'row_count' => $rowCount,
        ], 'exemption_requests');

        $rows = $query->with('latestTransition')->lazy(500)->map(fn (ExemptionRequest $item): array => [
            $item->getKey(),
            $item->target_course_code,
            __('credit_decisions.requirement_roles.'.$item->preparatory_requirement_role->value),
            __('credit_decisions.statuses.'.$item->status->value),
            $item->revision,
            $evidencePresenter->summary($item->evidence),
            $item->student_note,
            $item->latestTransition?->reason,
            $item->submitted_at?->toIso8601String(),
        ]);

        return $xlsx->download('exemption-requests.xlsx', $this->exportHeaders(), $rows, app()->getLocale() === 'ar');
    }

    /**
     * Create a manual Exemption Draft.
     *
     * @bodyParam target_course_id integer required A Preparatory Course in the Student's bound Curriculum. Example: 5
     * @bodyParam evidence object required Structured corrected or missing qualification evidence.
     * @bodyParam evidence.english_test string Example: IELTS
     * @bodyParam evidence.english_score float Example: 7.0
     * @bodyParam evidence.previous_degree string Example: MSc
     * @bodyParam evidence.is_ejust_graduate boolean Example: true
     * @bodyParam evidence.previous_ejust_program_id integer Example: 2
     * @bodyParam evidence.document_ids integer[] Supporting document IDs. Example: [41,42]
     * @bodyParam student_note string Student explanation. Example: Corrected score report.
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::DRAFT, status: 201)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function store(Request $request, StudentDirectory $students, ManualExemptionWorkflow $workflow): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $validated = $this->validateDraft($request);
        $created = $workflow->create($student, (int) $validated['target_course_id'], $validated['evidence'], $validated['student_note'] ?? null);

        return (new ExemptionRequestResource($created))->response()->setStatusCode(201);
    }

    /**
     * View one of my manual Exemption requests.
     *
     * @urlParam exemption_request_id integer required Example: 1
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::APPROVED)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function show(Request $request, ExemptionRequest $exemptionRequest, StudentDirectory $students): ExemptionRequestResource
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null || $student->studentId !== $exemptionRequest->student_id, 403);

        return new ExemptionRequestResource($exemptionRequest->load(['transitions', 'decision']));
    }

    /**
     * Revise an editable Draft or Returned request.
     *
     * @urlParam exemption_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam evidence object Structured qualification evidence.
     * @bodyParam evidence.english_test string Example: IELTS
     * @bodyParam evidence.english_score float Example: 7.0
     * @bodyParam evidence.previous_degree string Example: MSc
     * @bodyParam evidence.is_ejust_graduate boolean Example: true
     * @bodyParam evidence.previous_ejust_program_id integer Example: 2
     * @bodyParam evidence.document_ids integer[] Supporting document IDs. Example: [41,42]
     * @bodyParam student_note string Student explanation.
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::RETURNED)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function update(Request $request, ExemptionRequest $exemptionRequest, ManualExemptionWorkflow $workflow): ExemptionRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'student_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ...$this->evidenceRules($request, required: false),
        ]);
        abort_if(! array_key_exists('evidence', $validated) && ! array_key_exists('student_note', $validated), 422);
        $updated = $workflow->revise($exemptionRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated);

        return new ExemptionRequestResource($updated);
    }

    /**
     * Abandon a Draft request.
     *
     * @urlParam exemption_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::ABANDONED)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function destroy(Request $request, ExemptionRequest $exemptionRequest, ManualExemptionWorkflow $workflow): ExemptionRequestResource
    {
        $validated = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);

        return new ExemptionRequestResource($workflow->abandon($exemptionRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision']));
    }

    /**
     * Submit or resubmit an Exemption request.
     *
     * @urlParam exemption_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required A retry-stable UUID. Example: 98e597dd-8267-4bc1-934e-91f8324e0ec1
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::PENDING_ADVISOR)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function submit(Request $request, ExemptionRequest $exemptionRequest, ManualExemptionWorkflow $workflow): ExemptionRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);

        return new ExemptionRequestResource($workflow->submit($exemptionRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id']));
    }

    /** @return array<string, mixed> */
    private function validateDraft(Request $request): array
    {
        return $request->validate([
            'target_course_id' => ['required', 'integer', 'min:1'],
            ...$this->evidenceRules($request, required: true),
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function evidenceRules(Request $request, bool $required): array
    {
        return [
            'evidence' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'evidence.english_test' => ['sometimes', 'string', 'in:IELTS,TOEFL iBT'],
            'evidence.english_score' => [
                'sometimes',
                'numeric',
                'min:0',
                Rule::when($request->input('evidence.english_test') === 'IELTS', ['max:9'], ['max:120']),
            ],
            'evidence.previous_degree' => ['sometimes', 'string', 'in:BSc,MSc'],
            'evidence.is_ejust_graduate' => ['sometimes', 'boolean'],
            'evidence.previous_ejust_program_id' => ['sometimes', 'integer', 'min:1'],
            'evidence.document_ids' => ['sometimes', 'array'],
            'evidence.document_ids.*' => ['integer', 'min:1'],
        ];
    }

    private function query(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(),
            knownFilters: ['status', 'target_course_id', 'national_id'],
            readableFilters: ['status', 'target_course_id'],
            sortable: ['id', 'status', 'target_course_code', 'submitted_at', 'created_at', 'updated_at'],
            integerFilters: ['target_course_id'],
        );
    }

    /** @param Builder<ExemptionRequest> $query */
    private function apply($query, CollectionQuery $collection): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($collection->search !== null) {
            $query->whereRaw('LOWER(target_course_code) LIKE ?', ['%'.mb_strtolower($collection->search).'%']);
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /** @return list<string> */
    private function exportHeaders(): array
    {
        return [
            __('credit_decisions.export_headers.id'),
            __('credit_decisions.export_headers.course_code'),
            __('credit_decisions.export_headers.requirement_role'),
            __('credit_decisions.export_headers.status'),
            __('credit_decisions.export_headers.revision'),
            __('credit_decisions.export_headers.evidence'),
            __('credit_decisions.export_headers.student_note'),
            __('credit_decisions.export_headers.latest_reason'),
            __('credit_decisions.export_headers.submitted_at'),
        ];
    }
}
