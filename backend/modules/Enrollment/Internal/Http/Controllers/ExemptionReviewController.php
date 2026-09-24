<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Response as ResponseAttribute;
use Knuckles\Scribe\Attributes\ResponseField as ResponseFieldAttribute;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;
use Modules\Enrollment\Internal\Http\Documentation\ManualExemptionResponseExamples;
use Modules\Enrollment\Internal\Http\Resources\ExemptionRequestResource;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Support\ManualExemptionEvidencePresenter;
use Modules\Enrollment\Internal\Support\ManualExemptionWorkflow;
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

/** @group Manual Exemption Review */
class ExemptionReviewController
{
    /**
     * Advisor manual Exemption queue.
     *
     * @queryParam target_course_id string Comma-separated positive Target Course IDs. Example: 5,6
     * @queryParam student_id string Comma-separated positive Student anchor IDs. Example: 10,11
     * @queryParam q string Student, University-ID, or Course-code search. Example: أحمد
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::ADVISOR_QUEUE)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function advisorQueue(Request $request, StudentDirectory $students, Directory $directory): AnonymousResourceCollection
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $studentIds = $students->studentIdsAdvisedBy($actorId);
        $collection = $this->query($request);
        $query = ExemptionRequest::query()
            ->where('status', ExemptionRequestStatus::PendingAdvisor)
            ->whereIn('student_id', $studentIds);
        $this->apply($query, $collection, $students, $directory);

        return ExemptionRequestResource::collection($this->decorate($query->with(['transitions', 'decision'])->paginate($collection->perPage, page: $collection->page), $students, $directory));
    }

    /**
     * Export Advisor Exemption queue.
     *
     * @queryParam target_course_id string Comma-separated positive Target Course IDs. Example: 5,6
     * @queryParam student_id string Comma-separated positive Student anchor IDs. Example: 10,11
     * @queryParam q string Student, University-ID, or Course-code search. Example: PRE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function advisorExport(Request $request, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx, ManualExemptionEvidencePresenter $evidencePresenter): BinaryFileResponse
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $collection = $this->query($request);
        $query = ExemptionRequest::query()->where('status', ExemptionRequestStatus::PendingAdvisor)->whereIn('student_id', $students->studentIdsAdvisedBy($actorId));
        $this->apply($query, $collection, $students, $directory, includeSearch: false);

        return $this->export($query, $collection, $actorId, $students, $directory, $audit, $xlsx, $evidencePresenter, 'advisor-exemption-queue.xlsx');
    }

    /**
     * Department Head manual Exemption queue.
     *
     * Uses the same query contract as the Advisor queue.
     *
     * @queryParam target_course_id string Comma-separated positive Target Course IDs. Example: 5,6
     * @queryParam student_id string Comma-separated positive Student anchor IDs. Example: 10,11
     * @queryParam q string Student, University-ID, or Course-code search. Example: PRE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     * @queryParam include_decided boolean Include approved decision history for revocation. Example: true
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::DEPARTMENT_HEAD_QUEUE)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function departmentHeadQueue(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads): AnonymousResourceCollection
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $collection = $this->query($request);
        $query = $this->departmentHeadScopedQuery($actorId, $courses, $heads, $request->boolean('include_decided'));
        $this->apply($query, $collection, $students, $directory);

        return ExemptionRequestResource::collection($this->decorate($query->with(['transitions', 'decision.revocation'])->paginate($collection->perPage, page: $collection->page), $students, $directory));
    }

    /**
     * Export Department Head Exemption queue.
     *
     * @queryParam target_course_id string Comma-separated positive Target Course IDs. Example: 5,6
     * @queryParam student_id string Comma-separated positive Student anchor IDs. Example: 10,11
     * @queryParam q string Student, University-ID, or Course-code search. Example: PRE
     * @queryParam sort string Example: submitted_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function departmentHeadExport(Request $request, StudentDirectory $students, Directory $directory, CourseCatalog $courses, DepartmentHeadAssignments $heads, AuditLogger $audit, XlsxExporter $xlsx, ManualExemptionEvidencePresenter $evidencePresenter): BinaryFileResponse
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $collection = $this->query($request);
        $query = $this->departmentHeadScopedQuery($actorId, $courses, $heads);
        $this->apply($query, $collection, $students, $directory, includeSearch: false);

        return $this->export($query, $collection, $actorId, $students, $directory, $audit, $xlsx, $evidencePresenter, 'department-head-exemption-queue.xlsx');
    }

    /**
     * Advisor action on a current advisee's request.
     *
     * @urlParam exemption_request_id integer required Example: 1
     *
     * @bodyParam action string required recommend, return, or reject. Example: recommend
     * @bodyParam reason string Required for return/reject. Example: Upload a readable score report.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: c63e0602-5eb5-44a9-a8dd-056da2ae03ea
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::PENDING_HEAD)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function advisorDecision(Request $request, ExemptionRequest $exemptionRequest, ManualExemptionWorkflow $workflow): ExemptionRequestResource
    {
        $validated = $this->decisionInput($request, ['recommend', 'return', 'reject']);

        return new ExemptionRequestResource($workflow->advisorDecision($exemptionRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /**
     * Scoped Department Head final action.
     *
     * @urlParam exemption_request_id integer required Example: 1
     *
     * @bodyParam action string required approve, return, or reject. Example: approve
     * @bodyParam reason string Required for return/reject. Example: Evidence is incomplete.
     * @bodyParam expected_revision integer required Example: 1
     * @bodyParam action_id string required Retry-stable UUID. Example: c482238d-e722-4a61-9c97-aaf4aa7a441f
     */
    #[ResponseAttribute(content: ManualExemptionResponseExamples::APPROVED)]
    #[ResponseFieldAttribute(name: 'data.decision', type: 'object', description: 'The immutable effective decision when the request was approved.', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.student_note', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.submitted_at', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.from_status', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.transitions.reason', type: 'string', required: false, nullable: true)]
    #[ResponseFieldAttribute(name: 'data.decision.admission_application_id', type: 'integer', required: false, nullable: true)]
    public function departmentHeadDecision(Request $request, ExemptionRequest $exemptionRequest, ManualExemptionWorkflow $workflow): ExemptionRequestResource
    {
        $validated = $this->decisionInput($request, ['approve', 'return', 'reject']);

        return new ExemptionRequestResource($workflow->departmentHeadDecision($exemptionRequest, (int) $request->user()?->getAuthIdentifier(), (int) $validated['expected_revision'], $validated['action_id'], $validated['action'], $validated['reason'] ?? null));
    }

    /**
     * @param  list<string>  $actions
     * @return array<string, mixed>
     */
    private function decisionInput(Request $request, array $actions): array
    {
        return $request->validate([
            'action' => ['required', Rule::in($actions)],
            'reason' => [Rule::requiredIf(fn (): bool => in_array($request->input('action'), ['return', 'reject'], true)), 'nullable', 'string', 'max:2000'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'action_id' => ['required', 'uuid'],
        ]);
    }

    private function query(Request $request): CollectionQuery
    {
        return CollectionQuery::from(
            $request->query(),
            knownFilters: ['target_course_id', 'student_id', 'national_id'],
            readableFilters: ['target_course_id', 'student_id'],
            sortable: ['id', 'target_course_code', 'submitted_at', 'created_at', 'updated_at'],
            stableKey: 'id',
            integerFilters: ['target_course_id', 'student_id'],
        );
    }

    /** @return Builder<ExemptionRequest> */
    private function departmentHeadScopedQuery(int $actorId, CourseCatalog $courses, DepartmentHeadAssignments $heads, bool $includeDecided = false): Builder
    {
        $statuses = $includeDecided
            ? [ExemptionRequestStatus::PendingDepartmentHead, ExemptionRequestStatus::Approved]
            : [ExemptionRequestStatus::PendingDepartmentHead];
        $candidateCourseIds = ExemptionRequest::query()->whereIn('status', $statuses)->distinct()->pluck('target_course_id')->map(fn ($id): int => (int) $id)->all();
        $courseSummaries = $courses->findMany($candidateCourseIds);
        $headsCache = [];
        $scopedCourseIds = array_values(array_filter($candidateCourseIds, static function (int $id) use ($actorId, $courseSummaries, $heads, &$headsCache): bool {
            $course = $courseSummaries[$id] ?? null;
            if ($course === null) {
                return false;
            }

            $cacheKey = $course->departmentId ?? -1;
            if (! array_key_exists($cacheKey, $headsCache)) {
                $headsCache[$cacheKey] = $heads->headsForDepartment($course->departmentId);
            }

            return in_array($actorId, $headsCache[$cacheKey], true);
        }));

        return ExemptionRequest::query()->whereIn('status', $statuses)->whereIn('target_course_id', $scopedCourseIds);
    }

    /** @param Builder<ExemptionRequest> $query */
    private function apply(Builder $query, CollectionQuery $collection, StudentDirectory $students, Directory $directory, bool $includeSearch = true): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        if ($includeSearch && $collection->search !== null) {
            $needle = ArabicNormalizer::normalize($collection->search);
            $candidates = (clone $query)->get(['id', 'student_id', 'target_course_code']);
            $summaries = $students->findManyByStudentId($candidates->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn ($student): int => $student->userId, $summaries))));
            $ids = $candidates->filter(fn (ExemptionRequest $candidate): bool => $this->matchesSearch($candidate, $needle, $summaries, $users))->pluck('id')->all();
            $query->whereIn('id', $ids);
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    /**
     * @param  LengthAwarePaginator<int, ExemptionRequest>  $paginator
     * @return LengthAwarePaginator<int, ExemptionRequest>
     */
    private function decorate(LengthAwarePaginator $paginator, StudentDirectory $students, Directory $directory): LengthAwarePaginator
    {
        $studentSummaries = $students->findManyByStudentId($paginator->getCollection()->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $users = $directory->findMany(array_values(array_unique(array_map(fn ($student): int => $student->userId, $studentSummaries))));
        $paginator->setCollection($paginator->getCollection()->each(function (ExemptionRequest $request) use ($studentSummaries, $users): void {
            $student = $studentSummaries[$request->student_id] ?? null;
            $user = $student === null ? null : ($users[$student->userId] ?? null);
            $request->setAttribute('student_context', $student === null ? null : [
                'student_id' => $student->studentId,
                'university_id' => $student->universityId,
                'name' => $user?->name,
            ]);
        }));

        return $paginator;
    }

    /** @param Builder<ExemptionRequest> $query */
    private function export(Builder $query, CollectionQuery $collection, int $actorId, StudentDirectory $students, Directory $directory, AuditLogger $audit, XlsxExporter $xlsx, ManualExemptionEvidencePresenter $evidencePresenter, string $filename): BinaryFileResponse
    {
        $rowCount = 0;
        $rows = $this->exportRows($query, $students, $directory, $evidencePresenter, $collection->search, $rowCount);
        $response = $xlsx->download($filename, [
            __('credit_decisions.export_headers.id'),
            __('credit_decisions.export_headers.university_id'),
            __('credit_decisions.export_headers.student_name'),
            __('credit_decisions.export_headers.course_code'),
            __('credit_decisions.export_headers.requirement_role'),
            __('credit_decisions.export_headers.status'),
            __('credit_decisions.export_headers.revision'),
            __('credit_decisions.export_headers.evidence'),
            __('credit_decisions.export_headers.student_note'),
            __('credit_decisions.export_headers.latest_reason'),
            __('credit_decisions.export_headers.submitted_at'),
        ], $rows, app()->getLocale() === 'ar');
        $audit->record('exemption.requests.exported', $actorId, [
            'resource' => 'exemption_requests',
            'criteria' => $collection->auditCriteria(),
            'row_count' => $rowCount,
        ], 'exemption_requests');

        return $response;
    }

    /**
     * Resolve cross-module display fields in bounded batches while yielding export rows.
     *
     * @param  Builder<ExemptionRequest>  $query
     * @return \Generator<int, list<bool|float|int|string|null>>
     */
    private function exportRows(Builder $query, StudentDirectory $students, Directory $directory, ManualExemptionEvidencePresenter $evidencePresenter, ?string $search, int &$rowCount): \Generator
    {
        $needle = $search === null ? null : ArabicNormalizer::normalize($search);
        foreach ($query->with('latestTransition')->lazy(500)->chunk(500) as $requests) {
            $studentSummaries = $students->findManyByStudentId($requests->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
            $users = $directory->findMany(array_values(array_unique(array_map(fn ($student): int => $student->userId, $studentSummaries))));
            foreach ($requests as $item) {
                if ($needle !== null && ! $this->matchesSearch($item, $needle, $studentSummaries, $users)) {
                    continue;
                }
                $student = $studentSummaries[$item->student_id] ?? null;
                $user = $student === null ? null : ($users[$student->userId] ?? null);
                $rowCount++;
                yield [
                    $item->getKey(),
                    $student?->universityId,
                    $user?->name,
                    $item->target_course_code,
                    __('credit_decisions.requirement_roles.'.$item->preparatory_requirement_role->value),
                    __('credit_decisions.statuses.'.$item->status->value),
                    $item->revision,
                    $evidencePresenter->summary($item->evidence),
                    $item->student_note,
                    $item->latestTransition?->reason,
                    $item->submitted_at?->toIso8601String(),
                ];
            }
        }
    }

    /**
     * @param  array<int, StudentSummary>  $students
     * @param  array<int, UserSummary>  $users
     */
    private function matchesSearch(ExemptionRequest $request, string $needle, array $students, array $users): bool
    {
        $student = $students[$request->student_id] ?? null;
        $user = $student === null ? null : ($users[$student->userId] ?? null);
        $haystack = ArabicNormalizer::normalize(implode(' ', [$request->target_course_code, $student?->universityId, $user?->name]));

        return str_contains($haystack, $needle);
    }
}
