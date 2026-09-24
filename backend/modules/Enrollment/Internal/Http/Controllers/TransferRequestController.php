<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Http\Documentation\TransferResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\TransferRequestResource;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Support\TransferWorkflow;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Support\Export\XlsxExporter;
use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @group Transfers */
class TransferRequestController
{
    /**
     * My Transfer evidence.
     *
     * @response 200 {"data":[{"id":"01ARZ3NDEKTSV4RRFFQ69G5FAV","type":"transcript","original_filename":"transcript.pdf"}]}
     */
    public function evidenceDocuments(Request $request, StudentDirectory $students, AdmissionDocuments $documents): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return response()->json(['data' => collect($documents->forApplicant($student->userId))
            ->filter(fn ($document): bool => in_array($document->type, ['transcript', 'additional_certificate'], true))
            ->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type,
                'original_filename' => $document->originalFilename,
            ])->values()->all()]);
    }

    /**
     * Open Transfer evidence.
     *
     * The owner and only the reviewer whose live queue contains this request may
     * stream a referenced admission document. The Admissions facade rechecks that
     * the opaque document belongs to the Student's applicant account.
     *
     * @urlParam transfer_request_id integer required Example: 1
     * @urlParam document string required The opaque document ULID. Example: 01ARZ3NDEKTSV4RRFFQ69G5FAV
     *
     * @response 200 <<binary>>
     */
    public function evidenceDocument(
        Request $httpRequest,
        TransferRequest $transferRequest,
        string $document,
        StudentDirectory $students,
        Directory $directory,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        AdmissionDocuments $documents,
    ): StreamedResponse {
        abort_unless(in_array($document, $transferRequest->document_ids, true), 404);
        $student = $students->findByStudentId($transferRequest->student_id);
        abort_if($student === null, 404);
        $actorId = (int) $httpRequest->user()?->getAuthIdentifier();

        if ($student->userId !== $actorId) {
            $course = $courses->find($transferRequest->target_course_id);
            $authorized = ($transferRequest->status === TransferRequestStatus::PendingAdvisor
                    && $directory->hasRole($actorId, Role::Advisor)
                    && $student->advisorId === $actorId)
                || ($transferRequest->status === TransferRequestStatus::PendingDepartmentHead
                    && $directory->hasRole($actorId, Role::DepartmentHead)
                    && $course !== null
                    && in_array($actorId, $heads->headsForDepartment($course->departmentId), true))
                || ($transferRequest->status === TransferRequestStatus::PendingCouncil
                    && $directory->hasRole($actorId, Role::Council));
            abort_unless($authorized, 403);
        }

        $response = $documents->downloadForApplicant($student->userId, $document);
        abort_if($response === null, 404);

        return $response;
    }

    /**
     * My Transfer targets.
     *
     * @response 200 {"data":[{"target_course_id":5,"target_course_code":"CSE501","target_category":"ProgramCore","target_credit_hours":3,"already_transferred":false}]}
     */
    public function targets(Request $request, StudentDirectory $students, CurriculumCatalog $curricula): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $existing = TransferDecision::query()->where('student_id', $student->studentId)->pluck('target_course_id')->map(fn ($id): int => (int) $id)->all();

        return response()->json(['data' => collect($curricula->entriesForVersion($student->curriculumVersionId))->map(fn ($entry): array => [
            'target_course_id' => $entry->courseId,
            'target_course_code' => $entry->courseCode,
            'target_category' => $entry->category->value,
            'target_credit_hours' => $entry->totalCh,
            'already_transferred' => in_array($entry->courseId, $existing, true),
        ])->values()->all()]);
    }

    /**
     * My Transfer requests.
     *
     * @queryParam status string Comma-separated request statuses. Example: Draft,Returned
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam normalized_grade string Comma-separated normalized grades. Example: A,B+
     * @queryParam q string Course or external-evidence search. Example: Kyoto
     * @queryParam sort string Example: -updated_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: TransferResponseExamples::LIST)]
    public function index(Request $request, StudentDirectory $students): AnonymousResourceCollection
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $collection = $this->query($request);
        $query = TransferRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);

        return TransferRequestResource::collection($query->with(['transitions', 'decision'])->paginate($collection->perPage, page: $collection->page));
    }

    /**
     * Export my Transfer requests.
     *
     * @queryParam status string Example: Draft,Returned
     * @queryParam target_course_id string Example: 5,6
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: Kyoto
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
        $query = TransferRequest::query()->where('student_id', $student->studentId);
        $this->apply($query, $collection);
        $rowCount = (clone $query)->count();
        $audit->record('transfer.requests.exported', $actorId, [
            'resource' => 'transfer_requests',
            'criteria' => $collection->auditCriteria(),
            'row_count' => $rowCount,
        ], 'transfer_requests');
        $rows = $query->with('latestTransition')->lazy(500)->map(fn (TransferRequest $item): array => $this->exportRow($item));

        return $xlsx->download('transfer-requests.xlsx', $this->exportHeaders(), $rows, app()->getLocale() === 'ar');
    }

    /**
     * Create a Transfer Draft.
     *
     * @bodyParam target_course_id integer required A Course in the Student's bound Curriculum. Example: 5
     * @bodyParam external_institution string required Exact institution printed on the evidence. Example: Kyoto University
     * @bodyParam external_course_code string required Exact external Course code. Example: CS501
     * @bodyParam external_course_name string required Exact external Course name. Example: Algorithms
     * @bodyParam source_credit_hours float required Source hours retained only as evidence. Example: 4.00
     * @bodyParam external_grade string required Grade exactly as printed. Example: Excellent
     * @bodyParam document_ids string[] required Owned admission transcript/evidence ULIDs. Example: ["01ARZ3NDEKTSV4RRFFQ69G5FAV"]
     * @bodyParam student_note string Student explanation. Example: Equivalent syllabus attached.
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST, status: 201)]
    public function store(Request $request, StudentDirectory $students, TransferWorkflow $workflow): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $validated = $this->validateDraft($request);
        $created = $workflow->create($student, (int) $validated['target_course_id'], $this->evidence($validated), $validated['student_note'] ?? null);

        return (new TransferRequestResource($created))->response()->setStatusCode(201);
    }

    /**
     * View one of my Transfer requests.
     *
     * @urlParam transfer_request_id integer required Example: 1
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function show(Request $request, TransferRequest $transferRequest, StudentDirectory $students): TransferRequestResource
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null || $student->studentId !== $transferRequest->student_id, 403);

        return new TransferRequestResource($transferRequest->load(['transitions', 'decision']));
    }

    /**
     * Revise an editable Transfer request.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam external_institution string Exact institution printed on the evidence. Example: Kyoto University
     * @bodyParam external_course_code string Exact external Course code. Example: CS501
     * @bodyParam external_course_name string Exact external Course name. Example: Algorithms
     * @bodyParam source_credit_hours float Source hours retained only as evidence. Example: 4.00
     * @bodyParam external_grade string Grade exactly as printed. Example: Excellent
     * @bodyParam document_ids string[] Supporting transcript/evidence ULIDs. Example: ["01ARZ3NDEKTSV4RRFFQ69G5FAV"]
     * @bodyParam student_note string Student explanation; nullable. Example: Updated evidence attached.
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function update(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            ...$this->evidenceRules(required: false),
            'student_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        abort_if(count(array_diff(array_keys($validated), ['expected_revision'])) === 0, 422);

        return new TransferRequestResource($workflow->revise($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated));
    }

    /**
     * Abandon a Transfer Draft.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function destroy(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);

        return new TransferRequestResource($workflow->abandon($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision']));
    }

    /**
     * Submit or resubmit a Transfer request.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: 11111111-1111-4111-8111-111111111111
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function submit(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);

        return new TransferRequestResource($workflow->submit($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id']));
    }

    /** @return array<string, mixed> */
    private function validateDraft(Request $request): array
    {
        return $request->validate([
            'target_course_id' => ['required', 'integer', 'min:1'],
            ...$this->evidenceRules(required: true),
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function evidenceRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'external_institution' => [$presence, 'string', 'max:500'],
            'external_course_code' => [$presence, 'string', 'max:200'],
            'external_course_name' => [$presence, 'string', 'max:500'],
            'source_credit_hours' => [$presence, 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'external_grade' => [$presence, 'string', 'max:200'],
            'document_ids' => [$presence, 'array', 'min:1'],
            'document_ids.*' => ['string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function evidence(array $validated): array
    {
        return array_intersect_key($validated, array_flip([
            'external_institution', 'external_course_code', 'external_course_name',
            'source_credit_hours', 'external_grade', 'document_ids',
        ]));
    }

    private function query(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(),
            knownFilters: ['status', 'target_course_id', 'normalized_grade', 'national_id'],
            readableFilters: ['status', 'target_course_id', 'normalized_grade'],
            sortable: ['id', 'status', 'target_course_code', 'external_institution', 'normalized_grade', 'submitted_at', 'created_at', 'updated_at'],
            integerFilters: ['target_course_id'],
        );
    }

    /** @param Builder<TransferRequest> $query */
    private function apply(Builder $query, CollectionQuery $collection): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($collection->search !== null) {
            $needle = ArabicNormalizer::normalize($collection->search);
            $query->whereIn('id', (clone $query)->get()->filter(function (TransferRequest $candidate) use ($needle): bool {
                $haystack = ArabicNormalizer::normalize(implode(' ', [
                    $candidate->target_course_code,
                    $candidate->external_institution,
                    $candidate->external_course_code,
                    $candidate->external_course_name,
                    $candidate->external_grade,
                ]));

                return str_contains($haystack, $needle);
            })->pluck('id')->all());
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /** @return list<string> */
    private function exportHeaders(): array
    {
        return array_map(fn (string $key): string => __('credit_decisions.transfer.export_headers.'.$key), [
            'id', 'target_course', 'target_category', 'target_credit_hours', 'external_institution',
            'external_course_code', 'external_course_name', 'source_credit_hours', 'external_grade',
            'normalized_grade', 'status', 'revision', 'document_ids', 'student_note', 'latest_reason', 'submitted_at',
        ]);
    }

    /** @return list<int|string|null> */
    private function exportRow(TransferRequest $item): array
    {
        return [
            $item->getKey(), $item->target_course_code,
            __('credit_decisions.transfer.categories.'.$item->target_category->value),
            $item->target_credit_hours, $item->external_institution, $item->external_course_code,
            $item->external_course_name, $item->source_credit_hours, $item->external_grade,
            $item->normalized_grade, __('credit_decisions.transfer.statuses.'.$item->status->value),
            $item->revision, implode(', ', $item->document_ids), $item->student_note,
            $item->latestTransition?->reason, $item->submitted_at?->toIso8601String(),
        ];
    }
}
