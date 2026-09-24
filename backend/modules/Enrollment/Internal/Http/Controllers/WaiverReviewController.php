<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Knuckles\Scribe\Attributes\ResponseField as ResponseFieldAttribute;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Http\Documentation\WaiverResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\WaiverRequestResource;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Support\WaiverWorkflow;
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

/** @group Waiver Review */
class WaiverReviewController
{
    public function __construct(private readonly AdmissionDocuments $admissionDocuments) {}

    /**
     * Advisor Waiver queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     * @queryParam include_decided boolean Include approved decision history for revocation. Example: true
     */
    #[ResponseAttribute(content: WaiverResponseExamples::REVIEW_LIST)]
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
    public function advisorQueue(Request $request, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $actor = (int) $request->user()?->getAuthIdentifier();

        return $this->page($request, WaiverRequest::query()->where('status', WaiverRequestStatus::PendingAdvisor)->whereIn('student_id', $students->studentIdsAdvisedBy($actor)), $students, $directory);
    }

    /**
     * Department Head Waiver queue.
     *
     * Uses the same query contract as the Advisor queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: WaiverResponseExamples::REVIEW_LIST)]
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
    public function departmentHeadQueue(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads): AnonymousResourceCollection
    {
        return $this->page($request, $this->headQuery((int) $request->user()?->getAuthIdentifier(), $courses, $heads), $students, $directory);
    }

    /**
     * Council Waiver queue.
     *
     * Uses the same query contract as the Advisor queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: WaiverResponseExamples::REVIEW_LIST)]
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
    public function councilQueue(Request $request, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $statuses = $request->boolean('include_decided')
            ? [WaiverRequestStatus::PendingCouncil, WaiverRequestStatus::Approved]
            : [WaiverRequestStatus::PendingCouncil];

        return $this->page($request, WaiverRequest::query()->whereIn('status', $statuses), $students, $directory);
    }

    /**
     * Export Advisor Waiver queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function advisorExport(Request $request, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();

        return $this->export($request, WaiverRequest::query()->where('status', WaiverRequestStatus::PendingAdvisor)->whereIn('student_id', $students->studentIdsAdvisedBy($actor)), $actor, $students, $directory, $audit, $xlsx, 'advisor-waiver-queue.xlsx');
    }

    /**
     * Export Department Head Waiver queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function departmentHeadExport(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();

        return $this->export($request, $this->headQuery($actor, $courses, $heads), $actor, $students, $directory, $audit, $xlsx, 'department-head-waiver-queue.xlsx');
    }

    /**
     * Export Council Waiver queue.
     *
     * @queryParam target_course_id string Comma-separated positive target Course IDs. Example: 5,6
     * @queryParam prior_course_id string Comma-separated positive prior Course IDs. Example: 8,9
     * @queryParam student_id string Comma-separated positive Student IDs. Example: 10,11
     * @queryParam q string Student, University-ID, target-code, or prior-code search. Example: CSE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function councilExport(Request $request, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();

        return $this->export($request, WaiverRequest::query()->where('status', WaiverRequestStatus::PendingCouncil), $actor, $students, $directory, $audit, $xlsx, 'council-waiver-queue.xlsx');
    }

    /**
     * Advisor action on a Waiver request.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam action string required recommend, return, or reject. Example: recommend
     * @bodyParam reason string Required for return/reject.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function advisorDecision(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $this->decision($request, ['recommend', 'return', 'reject']);

        return new WaiverRequestResource($workflow->advisorDecision($waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /**
     * Scoped Department Head verification.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam action string required verify, return, or reject. Example: verify
     * @bodyParam coverage_percentage number Required for verify; minimum 80. Example: 80
     * @bodyParam equivalency_note string Required for verify.
     * @bodyParam reason string Required for return/reject.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function departmentHeadDecision(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $this->decision($request, ['verify', 'return', 'reject'], true);

        return new WaiverRequestResource($workflow->departmentHeadDecision(
            $waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'],
            $validated['action_id'], $validated['action'], $validated['reason'] ?? null,
            isset($validated['coverage_percentage']) ? (float) $validated['coverage_percentage'] : null,
            $validated['equivalency_note'] ?? null,
        ));
    }

    /**
     * Academic Affairs Council final decision.
     *
     * @urlParam waiver_request_id integer required Example: 1
     *
     * @bodyParam action string required approve, return, or reject. Example: approve
     * @bodyParam reason string Required for return/reject.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID.
     */
    #[ResponseAttribute(content: WaiverResponseExamples::ITEM)]
    public function councilDecision(Request $request, WaiverRequest $waiverRequest, WaiverWorkflow $workflow): WaiverRequestResource
    {
        $validated = $this->decision($request, ['approve', 'return', 'reject']);

        return new WaiverRequestResource($workflow->councilDecision($waiverRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /** @param list<string> $actions
     * @return array<string, mixed>
     */
    private function decision(Request $request, array $actions, bool $head = false): array
    {
        return $request->validate([
            'action' => ['required', Rule::in($actions)],
            'reason' => [Rule::requiredIf(fn (): bool => in_array($request->input('action'), ['return', 'reject'], true)), 'nullable', 'string', 'max:2000'],
            'coverage_percentage' => [Rule::requiredIf(fn (): bool => $head && $request->input('action') === 'verify'), 'nullable', 'numeric', 'min:0', 'max:100'],
            'equivalency_note' => [Rule::requiredIf(fn (): bool => $head && $request->input('action') === 'verify'), 'nullable', 'string', 'max:2000'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);
    }

    private function collection(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(), knownFilters: ['target_course_id', 'prior_course_id', 'student_id', 'national_id'],
            readableFilters: ['target_course_id', 'prior_course_id', 'student_id'],
            sortable: ['id', 'target_course_code', 'prior_course_code', 'submitted_at', 'created_at', 'updated_at'],
            integerFilters: ['target_course_id', 'prior_course_id', 'student_id'],
        );
    }

    /** @param Builder<WaiverRequest> $query */
    private function page(Request $request, Builder $query, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $collection = $this->collection($request);
        $this->apply($query, $collection, $students, $directory);
        $paginator = $query->with(['transitions', 'decision.revocation'])->paginate($collection->perPage, page: $collection->page);

        return WaiverRequestResource::collection($this->decorate($paginator, $students, $directory));
    }

    /** @return Builder<WaiverRequest> */
    private function headQuery(int $actor, CourseCatalog $courses, DepartmentHeadAssignments $heads): Builder
    {
        $ids = WaiverRequest::query()->where('status', WaiverRequestStatus::PendingDepartmentHead)->distinct()->pluck('target_course_id')->map(fn ($id): int => (int) $id)->all();
        $resolved = $courses->findMany($ids);
        $headsCache = [];
        $scoped = array_values(array_filter($ids, static function (int $id) use ($actor, $resolved, $heads, &$headsCache): bool {
            $course = $resolved[$id] ?? null;
            if ($course === null) {
                return false;
            }

            $cacheKey = $course->departmentId ?? -1;
            if (! array_key_exists($cacheKey, $headsCache)) {
                $headsCache[$cacheKey] = $heads->headsForDepartment($course->departmentId);
            }

            return in_array($actor, $headsCache[$cacheKey], true);
        }));

        return WaiverRequest::query()->where('status', WaiverRequestStatus::PendingDepartmentHead)->whereIn('target_course_id', $scoped);
    }

    /** @param Builder<WaiverRequest> $query */
    private function apply(Builder $query, CollectionQuery $collection, StudentDirectory $students, Directory $directory, bool $search = true): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($search && $collection->search !== null) {
            $needle = ArabicNormalizer::normalize($collection->search);
            $candidates = (clone $query)->get(['id', 'student_id', 'target_course_code', 'prior_course_code']);
            $summaries = $students->findManyByStudentId($candidates->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
            $ids = $candidates->filter(fn (WaiverRequest $item): bool => $this->matches($item, $needle, $summaries, $users))->pluck('id')->all();
            $query->whereIn('id', $ids);
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /** @param LengthAwarePaginator<int, WaiverRequest> $paginator
     * @return LengthAwarePaginator<int, WaiverRequest>
     */
    private function decorate(LengthAwarePaginator $paginator, StudentDirectory $students, Directory $directory): LengthAwarePaginator
    {
        $summaries = $students->findManyByStudentId($paginator->getCollection()->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
        $paginator->setCollection($paginator->getCollection()->each(function (WaiverRequest $item) use ($summaries, $users): void {
            $student = $summaries[$item->student_id] ?? null;
            $user = $student === null ? null : ($users[$student->userId] ?? null);
            $item->setAttribute('student_context', $student === null ? null : ['student_id' => $student->studentId, 'university_id' => $student->universityId, 'name' => $user?->name]);
            $documentIds = array_flip($item->evidence['official_transcript_document_ids'] ?? []);
            $item->setAttribute('evidence_documents', $student === null ? [] : collect($this->admissionDocuments->forApplicant($student->userId))
                ->filter(fn ($document): bool => isset($documentIds[$document->id]))
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'original_filename' => $document->originalFilename,
                    'download_url' => route('enrollment.waiver-evidence.download', [
                        'waiver_request' => $item->getKey(),
                        'document' => $document->id,
                    ], absolute: false),
                ])->values()->all());
        }));

        return $paginator;
    }

    /** @param array<int, StudentSummary> $students
     * @param  array<int, UserSummary>  $users
     */
    private function matches(WaiverRequest $item, string $needle, array $students, array $users): bool
    {
        $student = $students[$item->student_id] ?? null;
        $user = $student === null ? null : ($users[$student->userId] ?? null);

        return str_contains(ArabicNormalizer::normalize(implode(' ', [$item->target_course_code, $item->prior_course_code, $student?->universityId, $user?->name])), $needle);
    }

    /** @param Builder<WaiverRequest> $query */
    private function export(Request $request, Builder $query, int $actor, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx, string $filename): BinaryFileResponse
    {
        $collection = $this->collection($request);
        $this->apply($query, $collection, $students, $directory, false);
        $rowCount = 0;
        $rows = $this->exportRows($query, $students, $directory, $collection->search, $rowCount);
        $response = $xlsx->download($filename, [__('credit_decisions.waiver.export.id'), __('credit_decisions.waiver.export.university_id'), __('credit_decisions.waiver.export.student'), __('credit_decisions.waiver.export.target_course'), __('credit_decisions.waiver.export.prior_course'), __('credit_decisions.waiver.export.coverage'), __('credit_decisions.waiver.export.status'), __('credit_decisions.waiver.export.latest_reason')], $rows, app()->getLocale() === 'ar');
        $audit->record('waiver.requests.exported', $actor, ['resource' => 'waiver_requests', 'criteria' => $collection->auditCriteria(), 'row_count' => $rowCount], 'waiver_requests');

        return $response;
    }

    /**
     * Resolve cross-module display fields in bounded batches while yielding export rows.
     *
     * @param  Builder<WaiverRequest>  $query
     * @return \Generator<int, list<float|int|string|null>>
     */
    private function exportRows(Builder $query, StudentDirectory $students, Directory $directory, ?string $search, int &$rowCount): \Generator
    {
        $needle = $search === null ? null : ArabicNormalizer::normalize($search);
        foreach ($query->with('latestTransition')->lazy(500)->chunk(500) as $chunk) {
            $summaries = $students->findManyByStudentId($chunk->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn (StudentSummary $student): int => $student->userId, $summaries))));
            foreach ($chunk as $item) {
                if ($needle !== null && ! $this->matches($item, $needle, $summaries, $users)) {
                    continue;
                }
                $student = $summaries[$item->student_id] ?? null;
                $user = $student === null ? null : ($users[$student->userId] ?? null);
                $rowCount++;
                yield [$item->getKey(), $student?->universityId, $user?->name, $item->target_course_code, $item->prior_course_code, $item->coverage_percentage, __('credit_decisions.statuses.'.$item->status->value), $item->latestTransition?->reason];
            }
        }
    }
}
