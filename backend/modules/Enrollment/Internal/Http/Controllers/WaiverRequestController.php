<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Knuckles\Scribe\Attributes\ResponseField as ResponseFieldAttribute;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Http\Documentation\WaiverResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\WaiverRequestResource;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Support\WaiverWorkflow;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Support\Export\XlsxExporter;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @group Waivers */
class WaiverRequestController
{
    /**
     * My Waiver targets.
     *
     * @response 200 {"data": [{"target_course_id": 5, "target_course_code": "CSE511", "category": "Core", "credit_hours": 3, "already_recognized": false}]}
     */
    public function targets(Request $request, StudentDirectory $students, CurriculumCatalog $curricula): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $recognized = ExemptionDecision::query()
            ->where('student_id', $student->studentId)
            ->pluck('target_course_id')
            ->merge(TransferDecision::query()->where('student_id', $student->studentId)->pluck('target_course_id'))
            ->merge(WaiverDecision::query()->where('student_id', $student->studentId)->pluck('target_course_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        $rows = collect($curricula->entriesForVersion($student->curriculumVersionId))
            ->map(fn ($entry): array => [
                'target_course_id' => $entry->courseId,
                'target_course_code' => $entry->courseCode,
                'category' => $entry->category->value,
                'credit_hours' => $entry->totalCh,
                'already_recognized' => in_array($entry->courseId, $recognized, true),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * My qualifying PRS results.
     *
     * Only earned results whose Course is level 400 or above are offered. The earned
     * flag is the authoritative Grading-owned C+-or-higher classification.
     *
     * @response 200 {"data": [{"enrollment_id": 12, "course": {"id": 8, "code": "CSE411", "name_en": "Algorithms", "name_ar": "الخوارزميات", "level": 400, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 2}, "minimum_result": "C+"}]}
     */
    #[ResponseFieldAttribute(name: 'data.course.department_id', type: 'integer', required: false, nullable: true)]
    public function priorResults(Request $request, StudentDirectory $students, CourseCatalog $courses): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $enrollments = Enrollment::query()
            ->where('student_id', $student->studentId)
            ->where('earned', true)
            ->with('offering')
            ->orderBy('id')
            ->get();
        $courseIds = $enrollments->pluck('offering.course_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $resolved = $courses->findMany($courseIds);
        $rows = $enrollments->map(function (Enrollment $enrollment) use ($resolved): ?array {
            $course = $resolved[$enrollment->offering->course_id] ?? null;
            if ($course === null) {
                return null;
            }

            return [
                'enrollment_id' => $enrollment->getKey(),
                'course' => $course->toArray(),
                'minimum_result' => 'C+',
            ];
        })->filter()->values()->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * My Waiver transcript evidence.
     *
     * @response 200 {"data":[{"id":"01ARZ3NDEKTSV4RRFFQ69G5FAV","type":"transcript","original_filename":"transcript.pdf"}]}
     */
    public function evidenceDocuments(Request $request, StudentDirectory $students, AdmissionDocuments $documents): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return response()->json(['data' => collect($documents->forApplicant($student->userId))
            ->filter(fn ($document): bool => $document->type === 'transcript')
            ->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type,
                'original_filename' => $document->originalFilename,
            ])->values()->all()]);
    }

    /**
     * Open Waiver transcript evidence.
     *
     * @urlParam waiver_request_id integer required Example: 1
     * @urlParam document string required The opaque document ULID. Example: 01ARZ3NDEKTSV4RRFFQ69G5FAV
     *
     * @response 200 <<binary>>
     */
    public function evidenceDocument(
        Request $httpRequest,
        WaiverRequest $waiverRequest,
        string $document,
        StudentDirectory $students,
        Directory $directory,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        AdmissionDocuments $documents,
    ): StreamedResponse {
        $documentIds = $waiverRequest->evidence['official_transcript_document_ids'] ?? [];
        abort_unless(in_array($document, $documentIds, true), 404);
        $student = $students->findByStudentId($waiverRequest->student_id);
        abort_if($student === null, 404);
        $actorId = (int) $httpRequest->user()?->getAuthIdentifier();

        if ($student->userId !== $actorId) {
            $course = $courses->find($waiverRequest->target_course_id);
            $authorized = ($waiverRequest->status === WaiverRequestStatus::PendingAdvisor
                    && $directory->hasRole($actorId, Role::Advisor)
                    && $student->advisorId === $actorId)
                || ($waiverRequest->status === WaiverRequestStatus::PendingDepartmentHead
                    && $directory->hasRole($actorId, Role::DepartmentHead)
                    && $course !== null
                    && in_array($actorId, $heads->headsForDepartment($course->departmentId), true))
                || ($waiverRequest->status === WaiverRequestStatus::PendingCouncil
                    && $directory->hasRole($actorId, Role::Council));
            abort_unless($authorized, 403);
        }

        $response = $documents->downloadForApplicant($student->userId, $document);
        abort_if($response === null, 404);

        return $response;
    }

    /**
     * My Waiver requests.
     *
     * @queryParam status string Comma-separated request statuses. Example: Draft,Returned
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5
     * @queryParam q string Course-code search. Example: CSE
     * @queryParam sort string Example: -updated_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: WaiverResponseExamples::LIST)]
    #[ResponseFieldAttribute(name: 'data.prior_enrollment_id', type: 'integer', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.prior_letter_grade', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.head_verification', type: 'object', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.verification_snapshot', type: 'object', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective Waiver decision after Council approval.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.evidence.enrollment_id', type: 'integer', required: false, nullable: true)]
    public function index(Request $request, StudentDirectory $students): AnonymousResourceCollection
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $collection = $this->query($request);
        $query = WaiverRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);

        return WaiverRequestResource::collection($query->with(['transitions', 'decision'])->paginate($collection->perPage, page: $collection->page));
    }

    /**
     * Export my Waiver requests.
     *
     * @queryParam status string Comma-separated request statuses.
     * @queryParam target_course_id string Comma-separated positive target Course IDs.
     * @queryParam q string Course-code search.
     * @queryParam sort string Example: -updated_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, StudentDirectory $students, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $student = $students->find($actorId);
        abort_if($student === null, 403);
        $collection = $this->query($request);
        $query = WaiverRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);
        $count = (clone $query)->count();
        $audit->record('waiver.requests.exported', $actorId, [
            'resource' => 'waiver_requests', 'criteria' => $collection->auditCriteria(), 'row_count' => $count,
        ], 'waiver_requests');
        $rows = $query->with('latestTransition')->lazy(500)->map(fn (WaiverRequest $item): array => $this->exportRow($item));

        return $xlsx->download('waiver-requests.xlsx', $this->headers(), $rows, app()->getLocale() === 'ar');
    }

    /**
     * Create a Waiver Draft.
     *
     * @bodyParam target_course_id integer required Target Course in the bound Curriculum. Example: 5
     * @bodyParam prior_result object required Prior E-JUST result evidence.
     * @bodyParam prior_result.source string required prs or legacy_transcript. Example: prs
     * @bodyParam prior_result.institution string required Must be E-JUST. Example: E-JUST
     * @bodyParam prior_result.enrollment_id integer PRS Enrollment for the prior result. Example: 12
     * @bodyParam prior_result.official_transcript_document_ids string[] Owned official transcript ULIDs for a legacy result. Example: ["01ARZ3NDEKTSV4RRFFQ69G5FAW"]
     * @bodyParam student_note string Student explanation.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::DRAFT, status: 201)]
    public function store(Request $request, StudentDirectory $students, WaiverWorkflow $workflow): JsonResponse
    {
        $validated = $request->validate([
            'target_course_id' => ['required', 'integer', 'min:1'],
            'prior_result' => ['required', 'array'],
            'prior_result.source' => ['required', 'string'],
            'prior_result.institution' => ['required', 'string', 'max:100'],
            'prior_result.enrollment_id' => ['nullable', 'integer', 'min:1'],
            'prior_result.course_id' => ['nullable', 'integer', 'min:1'],
            'prior_result.letter_grade' => ['nullable', 'string', 'max:3'],
            'prior_result.official_transcript_document_ids' => ['nullable', 'array', 'min:1'],
            'prior_result.official_transcript_document_ids.*' => ['string', 'ulid', 'distinct'],
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $created = $workflow->create($student, (int) $validated['target_course_id'], $validated['prior_result'], $validated['student_note'] ?? null);

        return (new WaiverRequestResource($created))->response()->setStatusCode(201);
    }

    /**
     * Submit or resubmit a Waiver request.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function submit(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);

        return new WaiverRequestResource($workflow->submit($waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id']));
    }

    /**
     * Revise an editable Waiver request.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam prior_result object Revised prior E-JUST result evidence.
     * @bodyParam prior_result.official_transcript_document_ids string[] Owned official transcript ULIDs for a legacy result. Example: ["01ARZ3NDEKTSV4RRFFQ69G5FAW"]
     * @bodyParam student_note string Revised Student explanation.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function update(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'prior_result' => ['sometimes', 'array'],
            'prior_result.source' => ['required_with:prior_result', 'string'],
            'prior_result.institution' => ['required_with:prior_result', 'string', 'max:100'],
            'prior_result.enrollment_id' => ['nullable', 'integer', 'min:1'],
            'prior_result.course_id' => ['nullable', 'integer', 'min:1'],
            'prior_result.letter_grade' => ['nullable', 'string', 'max:3'],
            'prior_result.official_transcript_document_ids' => ['nullable', 'array', 'min:1'],
            'prior_result.official_transcript_document_ids.*' => ['string', 'ulid', 'distinct'],
            'student_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        abort_if(! array_key_exists('prior_result', $validated) && ! array_key_exists('student_note', $validated), 422);

        return new WaiverRequestResource($workflow->revise($waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated));
    }

    /**
     * View one of my Waiver requests.
     *
     * @urlParam waiver_request_id integer required Example: 1
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function show(Request $request, WaiverRequest $waiverRequest, StudentDirectory $students): WaiverRequestResource
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null || $student->studentId !== $waiverRequest->student_id, 403);

        return new WaiverRequestResource($waiverRequest->load(['transitions', 'decision']));
    }

    /**
     * Abandon a Waiver Draft.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function destroy(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);

        return new WaiverRequestResource($workflow->abandon($waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision']));
    }

    private function query(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(), knownFilters: ['status', 'target_course_id', 'national_id'],
            readableFilters: ['status', 'target_course_id'],
            sortable: ['id', 'status', 'target_course_code', 'prior_course_code', 'submitted_at', 'created_at', 'updated_at'],
            integerFilters: ['target_course_id'],
        );
    }

    /** @param Builder<WaiverRequest> $query */
    private function apply(Builder $query, CollectionQuery $collection): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($collection->search !== null) {
            $needle = '%'.mb_strtolower($collection->search).'%';
            $query->where(fn (Builder $nested) => $nested->whereRaw('LOWER(target_course_code) LIKE ?', [$needle])->orWhereRaw('LOWER(prior_course_code) LIKE ?', [$needle]));
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /** @return list<string> */
    private function headers(): array
    {
        return [
            __('credit_decisions.waiver.export.id'), __('credit_decisions.waiver.export.target_course'),
            __('credit_decisions.waiver.export.prior_course'), __('credit_decisions.waiver.export.source'),
            __('credit_decisions.waiver.export.grade'), __('credit_decisions.waiver.export.coverage'),
            __('credit_decisions.waiver.export.status'), __('credit_decisions.waiver.export.revision'),
            __('credit_decisions.waiver.export.student_note'), __('credit_decisions.waiver.export.latest_reason'),
            __('credit_decisions.waiver.export.submitted_at'),
        ];
    }

    /** @return list<float|int|string|null> */
    private function exportRow(WaiverRequest $item): array
    {
        return [
            $item->getKey(), $item->target_course_code, $item->prior_course_code,
            __('credit_decisions.waiver.sources.'.$item->prior_result_source), $item->prior_letter_grade,
            $item->coverage_percentage, __('credit_decisions.statuses.'.$item->status->value),
            $item->revision, $item->student_note, $item->latestTransition?->reason,
            $item->submitted_at?->toIso8601String(),
        ];
    }
}
