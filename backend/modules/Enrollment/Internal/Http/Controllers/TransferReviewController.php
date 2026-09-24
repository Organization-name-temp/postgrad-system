<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Http\Documentation\TransferResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\TransferRequestResource;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Support\TransferWorkflow;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;
use Support\Export\XlsxExporter;
use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Transfer Review */
class TransferReviewController
{
    public function __construct(private readonly AdmissionDocuments $admissionDocuments) {}

    /**
     * Advisor Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: أحمد
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: TransferResponseExamples::LIST)]
    public function advisorQueue(Request $request, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        return $this->list(
            $request,
            TransferRequest::query()->where('status', TransferRequestStatus::PendingAdvisor)
                ->whereIn('student_id', $students->studentIdsAdvisedBy((int) $request->user()?->getAuthIdentifier())),
            $students,
            $directory,
        );
    }

    /**
     * Export Advisor Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function advisorExport(Request $request, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        return $this->export(
            $request,
            TransferRequest::query()->where('status', TransferRequestStatus::PendingAdvisor)
                ->whereIn('student_id', $students->studentIdsAdvisedBy((int) $request->user()?->getAuthIdentifier())),
            $students,
            $directory,
            $audit,
            $xlsx,
            'advisor-transfer-queue.xlsx',
        );
    }

    /**
     * Department Head Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: TransferResponseExamples::LIST)]
    public function departmentHeadQueue(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads): AnonymousResourceCollection
    {
        return $this->list($request, $this->departmentHeadScopedQuery((int) $request->user()?->getAuthIdentifier(), $courses, $heads), $students, $directory);
    }

    /**
     * Export Department Head Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function departmentHeadExport(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        return $this->export($request, $this->departmentHeadScopedQuery((int) $request->user()?->getAuthIdentifier(), $courses, $heads), $students, $directory, $audit, $xlsx, 'department-head-transfer-queue.xlsx');
    }

    /**
     * Council Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     * @queryParam include_decided boolean Include approved decision history for revocation. Example: true
     */
    #[ResponseAttribute(content: TransferResponseExamples::LIST)]
    public function councilQueue(Request $request, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $statuses = $request->boolean('include_decided')
            ? [TransferRequestStatus::PendingCouncil, TransferRequestStatus::Approved, TransferRequestStatus::Rejected]
            : [TransferRequestStatus::PendingCouncil];

        return $this->list($request, TransferRequest::query()->whereIn('status', $statuses), $students, $directory);
    }

    /**
     * Export Council Transfer queue.
     *
     * @queryParam target_course_id string Example: 5,6
     * @queryParam student_id string Example: 10,11
     * @queryParam normalized_grade string Example: A,B+
     * @queryParam q string Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function councilExport(Request $request, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        return $this->export($request, TransferRequest::query()->where('status', TransferRequestStatus::PendingCouncil), $students, $directory, $audit, $xlsx, 'council-transfer-queue.xlsx');
    }

    /**
     * Advisor action on Transfer request.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam action string required recommend, return, or reject. Example: recommend
     * @bodyParam reason string Required for return/reject. Example: Upload the transcript.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: 22222222-2222-4222-8222-222222222222
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function advisorDecision(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $this->decisionInput($request, ['recommend', 'return', 'reject']);

        return new TransferRequestResource($workflow->advisorDecision($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /**
     * Scoped Head action on Transfer request.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam action string required verify, return, or reject. Example: verify
     * @bodyParam normalized_grade string Required for verify; one of A+, A, B+, B. Example: B+
     * @bodyParam reason string Required for return/reject. Example: Course content differs.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: 33333333-3333-4333-8333-333333333333
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function departmentHeadDecision(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $this->decisionInput($request, ['verify', 'return', 'reject'], head: true);

        return new TransferRequestResource($workflow->departmentHeadDecision($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null, $validated['normalized_grade'] ?? null));
    }

    /**
     * Council final Transfer action.
     *
     * @urlParam transfer_request_id integer required Example: 1
     *
     * @bodyParam action string required approve, return, or reject. Example: approve
     * @bodyParam reason string Required for return/reject. Example: Evidence is incomplete.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: 44444444-4444-4444-8444-444444444444
     */
    #[ResponseAttribute(content: TransferResponseExamples::REQUEST)]
    public function councilDecision(Request $request, TransferRequest $transferRequest, TransferWorkflow $workflow): TransferRequestResource
    {
        $validated = $this->decisionInput($request, ['approve', 'return', 'reject']);

        return new TransferRequestResource($workflow->councilDecision($transferRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /**
     * @param  list<string>  $actions
     * @return array<string, mixed>
     */
    private function decisionInput(Request $request, array $actions, bool $head = false): array
    {
        return $request->validate([
            'action' => ['required', Rule::in($actions)],
            'reason' => [Rule::requiredIf(fn (): bool => in_array($request->input('action'), ['return', 'reject'], true)), 'nullable', 'string', 'max:2000'],
            'normalized_grade' => [Rule::requiredIf($head && $request->input('action') === 'verify'), 'nullable', 'string'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);
    }

    private function query(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(),
            knownFilters: ['target_course_id', 'student_id', 'normalized_grade', 'national_id', 'include_decided'],
            readableFilters: ['target_course_id', 'student_id', 'normalized_grade'],
            sortable: ['id', 'target_course_code', 'external_institution', 'normalized_grade', 'submitted_at', 'created_at', 'updated_at'],
            integerFilters: ['target_course_id', 'student_id'],
        );
    }

    /** @param Builder<TransferRequest> $query */
    private function apply(Builder $query, CollectionQuery $collection, StudentDirectory $students, Directory $directory, bool $includeSearch = true): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($includeSearch && $collection->search !== null) {
            $needle = ArabicNormalizer::normalize($collection->search);
            $candidates = (clone $query)->get();
            $summaries = $students->findManyByStudentId($candidates->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
            $query->whereIn('id', $candidates->filter(fn (TransferRequest $candidate): bool => $this->matchesSearch($candidate, $needle, $summaries, $users))->pluck('id')->all());
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /** @param Builder<TransferRequest> $query */
    private function list(Request $request, Builder $query, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $collection = $this->query($request);
        $this->apply($query, $collection, $students, $directory);

        return TransferRequestResource::collection($this->decorate($query->with(['transitions', 'decision.revocation'])->paginate($collection->perPage, page: $collection->page), $students, $directory));
    }

    /** @param Builder<TransferRequest> $query */
    private function export(Request $request, Builder $query, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx, string $filename): BinaryFileResponse
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $collection = $this->query($request);
        $this->apply($query, $collection, $students, $directory, includeSearch: false);
        $rowCount = 0;
        $rows = $this->exportRows($query, $students, $directory, $collection->search, $rowCount);
        $response = $xlsx->download($filename, $this->exportHeaders(), $rows, app()->getLocale() === 'ar');
        $audit->record('transfer.requests.exported', $actorId, [
            'resource' => 'transfer_requests',
            'criteria' => $collection->auditCriteria(),
            'row_count' => $rowCount,
        ], 'transfer_requests');

        return $response;
    }

    /** @param Builder<TransferRequest> $query @return \Generator<int, list<int|string|null>> */
    private function exportRows(Builder $query, StudentDirectory $students, Directory $directory, ?string $search, int &$rowCount): \Generator
    {
        $needle = $search === null ? null : ArabicNormalizer::normalize($search);
        foreach ($query->with('latestTransition')->lazy(500)->chunk(500) as $requests) {
            $summaries = $students->findManyByStudentId($requests->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
            foreach ($requests as $item) {
                if ($needle !== null && ! $this->matchesSearch($item, $needle, $summaries, $users)) {
                    continue;
                }
                $student = $summaries[$item->student_id] ?? null;
                $user = $student === null ? null : ($users[$student->userId] ?? null);
                $rowCount++;
                yield [
                    $item->getKey(), $student?->universityId, $user?->name, $item->target_course_code,
                    $item->target_credit_hours, $item->external_institution, $item->external_course_code,
                    $item->external_course_name, $item->source_credit_hours, $item->external_grade,
                    $item->normalized_grade, __('credit_decisions.transfer.statuses.'.$item->status->value),
                    $item->revision, implode(', ', $item->document_ids), $item->student_note,
                    $item->latestTransition?->reason, $item->submitted_at?->toIso8601String(),
                ];
            }
        }
    }

    /** @return Builder<TransferRequest> */
    private function departmentHeadScopedQuery(int $actorId, CourseCatalog $courses, DepartmentHeadAssignments $heads): Builder
    {
        $courseIds = TransferRequest::query()->where('status', TransferRequestStatus::PendingDepartmentHead)->distinct()->pluck('target_course_id')->map(fn ($id): int => (int) $id)->all();
        $summaries = $courses->findMany($courseIds);
        $scoped = array_values(array_filter($courseIds, fn (int $id): bool => isset($summaries[$id]) && in_array($actorId, $heads->headsForDepartment($summaries[$id]->departmentId), true)));

        return TransferRequest::query()->where('status', TransferRequestStatus::PendingDepartmentHead)->whereIn('target_course_id', $scoped);
    }

    /**
     * @param  LengthAwarePaginator<int, TransferRequest>  $paginator
     * @return LengthAwarePaginator<int, TransferRequest>
     */
    private function decorate(LengthAwarePaginator $paginator, StudentDirectory $students, Directory $directory): LengthAwarePaginator
    {
        $summaries = $students->findManyByStudentId($paginator->getCollection()->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
        $paginator->setCollection($paginator->getCollection()->each(function (TransferRequest $request) use ($summaries, $users): void {
            $student = $summaries[$request->student_id] ?? null;
            $request->setAttribute('student_context', $student === null ? null : [
                'student_id' => $student->studentId,
                'university_id' => $student->universityId,
                'name' => $users[$student->userId]->name ?? null,
            ]);
            $documentIds = array_flip($request->document_ids);
            $request->setAttribute('evidence_documents', $student === null ? [] : collect($this->admissionDocuments->forApplicant($student->userId))
                ->filter(fn ($document): bool => isset($documentIds[$document->id]))
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'original_filename' => $document->originalFilename,
                    'download_url' => route('enrollment.transfer-evidence.download', [
                        'transfer_request' => $request->getKey(),
                        'document' => $document->id,
                    ], absolute: false),
                ])->values()->all());
        }));

        return $paginator;
    }

    /**
     * @param  array<int, StudentSummary>  $students
     * @param  array<int, UserSummary>  $users
     */
    private function matchesSearch(TransferRequest $request, string $needle, array $students, array $users): bool
    {
        $student = $students[$request->student_id] ?? null;
        $user = $student === null ? null : ($users[$student->userId] ?? null);
        $haystack = ArabicNormalizer::normalize(implode(' ', [
            $request->target_course_code, $request->external_institution, $request->external_course_code,
            $request->external_course_name, $request->external_grade, $student?->universityId, $user?->name,
        ]));

        return str_contains($haystack, $needle);
    }

    /** @return list<string> */
    private function exportHeaders(): array
    {
        return array_map(fn (string $key): string => __('credit_decisions.transfer.export_headers.'.$key), [
            'id', 'university_id', 'student_name', 'target_course', 'target_credit_hours',
            'external_institution', 'external_course_code', 'external_course_name', 'source_credit_hours',
            'external_grade', 'normalized_grade', 'status', 'revision', 'document_ids', 'student_note',
            'latest_reason', 'submitted_at',
        ]);
    }
}
