<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Internal\Support\StandingReconciler;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Public\AcademicRequestCloseout;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Contracts\StudentLifecycleWriter;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Support\Export\XlsxExporter;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Academic Standing */
final class TerminationCaseController
{
    /** @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingAdvisor","evidence":{"df_grades":[]},"advisor_user_id":null,"advisor_assessment":null,"advisor_recommendation":null,"department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}} */
    public function mine(Request $request, StudentDirectory $students, TerminationCases $cases): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return response()->json(['data' => $cases->latestForStudent($student->studentId)?->toArray()]);
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingAdvisor
     * @queryParam sort string Example: id
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingAdvisor","evidence":{"df_grades":[]},"advisor_user_id":null,"advisor_assessment":null,"advisor_recommendation":null,"department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function advisorQueue(Request $request, StudentDirectory $students): JsonResponse
    {
        $ids = $students->studentIdsAdvisedBy((int) $request->user()?->getAuthIdentifier());

        return $this->list($request, TerminationCase::query()->whereIn('student_id', $ids)->where('state', 'AwaitingAdvisor'));
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingAdvisor
     * @queryParam sort string Example: id
     *
     * @response 200 <<binary>>
     */
    public function advisorExport(Request $request, StudentDirectory $students, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        return $this->export($request, TerminationCase::query()->whereIn('student_id', $students->studentIdsAdvisedBy((int) $request->user()?->getAuthIdentifier()))->where('state', 'AwaitingAdvisor'), $audit, $xlsx, 'advisor-termination-cases.xlsx');
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingDepartmentHead
     * @queryParam sort string Example: id
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     *
     * @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingDepartmentHead","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function departmentQueue(Request $request, StudentDirectory $students, ProgramCatalog $programs, DepartmentHeadAssignments $heads): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $ids = array_values(array_filter($students->allStudentIds(), fn (int $studentId): bool => $this->isDepartmentHead($actor, $studentId, $students, $programs, $heads)));

        return $this->list($request, TerminationCase::query()->where('state', 'AwaitingDepartmentHead')->whereIn('student_id', $ids));
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingDepartmentHead
     * @queryParam sort string Example: id
     *
     * @response 200 <<binary>>
     */
    public function departmentExport(Request $request, StudentDirectory $students, ProgramCatalog $programs, DepartmentHeadAssignments $heads, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $ids = array_values(array_filter($students->allStudentIds(), fn (int $studentId): bool => $this->isDepartmentHead($actor, $studentId, $students, $programs, $heads)));

        return $this->export($request, TerminationCase::query()->where('state', 'AwaitingDepartmentHead')->whereIn('student_id', $ids), $audit, $xlsx, 'department-head-termination-cases.xlsx');
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingCouncil
     * @queryParam sort string Example: id
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Capped at 100. Example: 25
     * @queryParam include_decided boolean Include preserved final case history. Example: true
     *
     * @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingCouncil","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":null,"council_reason":null}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function councilQueue(Request $request): JsonResponse
    {
        $states = $request->boolean('include_decided') ? ['AwaitingCouncil', 'Dismissed', 'Terminated', 'TriggerCleared', 'EvidenceChanged'] : ['AwaitingCouncil'];

        return $this->list($request, TerminationCase::query()->whereIn('state', $states));
    }

    /**
     * @queryParam student_id string Example: 5,8
     * @queryParam state string Example: AwaitingCouncil
     * @queryParam sort string Example: id
     *
     * @response 200 <<binary>>
     */
    public function councilExport(Request $request, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        return $this->export($request, TerminationCase::query()->where('state', 'AwaitingCouncil'), $audit, $xlsx, 'council-termination-cases.xlsx');
    }

    /**
     * @bodyParam assessment string required Example: Two distinct failing grades remain effective.
     * @bodyParam recommendation string required continue or dismiss. Example: continue
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingDepartmentHead","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}}
     */
    public function advisorReview(Request $request, TerminationCase $case, StudentGuard $guard, TerminationCases $cases, AuditLogger $audit, Notifier $notifier): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $validated = $request->validate(['assessment' => ['required', 'string', 'max:4000'], 'recommendation' => ['required', Rule::in(['continue', 'dismiss'])]]);
        DB::transaction(function () use ($case, $guard, $cases, $actor, $validated, $notifier): void {
            $student = $guard->lock($case->student_id);
            abort_unless($student !== null && $student->advisorId === $actor, 404);
            abort_if($student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('academic_standing.lifecycle_inactive'));
            $current = $cases->reconcile($case->student_id);
            $locked = TerminationCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_if($current === null || $current->id !== $locked->id || $locked->state !== 'AwaitingAdvisor', 409, __('academic_standing.evidence_changed'));
            $locked->update(['state' => 'AwaitingDepartmentHead', 'advisor_user_id' => $actor, 'advisor_assessment' => $validated['assessment'], 'advisor_recommendation' => $validated['recommendation'], 'advisor_reviewed_at' => now()]);
            $this->notifyTransition($notifier, $student->userId, $student->advisorId, $locked, 'advisor_reviewed');
        });
        $audit->record('termination.advisor_reviewed', $actor, $validated, 'termination_case', (string) $case->id);

        return response()->json(['data' => $this->resource($case->fresh())]);
    }

    /**
     * @bodyParam action string required recommend or dismiss. Example: recommend
     * @bodyParam reason string required Example: Evidence is actionable.
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingCouncil","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":null,"council_reason":null}}
     */
    public function departmentReview(Request $request, TerminationCase $case, StudentGuard $guard, ProgramCatalog $programs, DepartmentHeadAssignments $heads, TerminationCases $cases, StandingReconciler $standing, AuditLogger $audit, Notifier $notifier): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $validated = $request->validate(['action' => ['required', Rule::in(['recommend', 'dismiss'])], 'reason' => ['required', 'string', 'max:4000']]);
        $state = DB::transaction(function () use ($case, $guard, $programs, $heads, $cases, $standing, $actor, $validated, $notifier): string {
            $student = $guard->lock($case->student_id);
            abort_if($student === null || $student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('academic_standing.lifecycle_inactive'));
            $program = $programs->find($student->programId);
            abort_unless($program !== null && in_array($actor, $heads->headsForDepartment($program->departmentId), true), 404);
            $current = $cases->reconcile($case->student_id);
            $locked = TerminationCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_if($current === null || $current->id !== $locked->id || $locked->state !== 'AwaitingDepartmentHead', 409, __('academic_standing.evidence_changed'));
            $state = $validated['action'] === 'recommend' ? 'AwaitingCouncil' : 'Dismissed';
            $locked->update(['state' => $state, 'department_head_user_id' => $actor, 'department_head_reason' => $validated['reason'], 'department_head_reviewed_at' => now(), 'closed_reason' => $state === 'Dismissed' ? 'Dismissed' : null, 'closed_at' => $state === 'Dismissed' ? now() : null]);
            $this->notifyTransition($notifier, $student->userId, $student->advisorId, $locked, 'department_reviewed');
            if ($state === 'Dismissed') {
                $standing->reconcile($case->student_id, 'termination_case', $case->id);
            }

            return $state;
        });
        $audit->record('termination.department_reviewed', $actor, ['state' => $state, 'reason' => $validated['reason']], 'termination_case', (string) $case->id);

        return response()->json(['data' => $this->resource($case->fresh())]);
    }

    /**
     * @bodyParam action string required terminate or dismiss. Example: terminate
     * @bodyParam reason string required Example: Council confirmed termination.
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"Terminated","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":8,"council_reason":"Confirmed."}}
     */
    public function councilDecision(Request $request, TerminationCase $case, StudentGuard $guard, StudentLifecycleWriter $lifecycle, AcademicRequestCloseout $closeout, TerminationCases $cases, StandingReconciler $standing, AuditLogger $audit, Notifier $notifier): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $validated = $request->validate(['action' => ['required', Rule::in(['terminate', 'dismiss'])], 'reason' => ['required', 'string', 'max:4000']]);
        DB::transaction(function () use ($case, $actor, $validated, $guard, $lifecycle, $closeout, $cases, $standing, $notifier): void {
            $student = $guard->lock($case->student_id);
            abort_if($student === null || $student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('academic_standing.lifecycle_inactive'));
            $current = $cases->reconcile($case->student_id);
            $locked = TerminationCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_if($current === null || $current->id !== $locked->id || $locked->state !== 'AwaitingCouncil', 409, __('academic_standing.evidence_changed'));
            $state = $validated['action'] === 'terminate' ? 'Terminated' : 'Dismissed';
            if ($state === 'Terminated') {
                $lifecycle->terminate($case->student_id);
                $closeout->forTermination($case->student_id, $case->id);
            }
            $locked->update(['state' => $state, 'council_user_id' => $actor, 'council_reason' => $validated['reason'], 'council_decided_at' => now(), 'closed_reason' => $state, 'closed_at' => now()]);
            $this->notifyTransition($notifier, $student->userId, $student->advisorId, $locked, 'council_decided');
            $standing->reconcile($case->student_id, 'termination_case', $case->id);
        });
        $audit->record('termination.council_decided', $actor, ['state' => $case->fresh()->state, 'reason' => $validated['reason']], 'termination_case', (string) $case->id);

        return response()->json(['data' => $this->resource($case->fresh())]);
    }

    private function isDepartmentHead(int $actor, int $studentId, StudentDirectory $students, ProgramCatalog $programs, DepartmentHeadAssignments $heads): bool
    {
        $student = $students->findByStudentId($studentId);
        $program = $student === null ? null : $programs->find($student->programId);

        return $program !== null && in_array($actor, $heads->headsForDepartment($program->departmentId), true);
    }

    /** @return array<string, mixed> */
    private function resource(TerminationCase $case): array
    {
        return ['id' => $case->id, 'student_id' => $case->student_id, 'fingerprint' => $case->fingerprint, 'state' => $case->state, 'evidence' => $case->evidence, 'advisor_user_id' => $case->getAttribute('advisor_user_id'), 'advisor_assessment' => $case->getAttribute('advisor_assessment'), 'advisor_recommendation' => $case->getAttribute('advisor_recommendation'), 'department_head_user_id' => $case->getAttribute('department_head_user_id'), 'department_head_reason' => $case->getAttribute('department_head_reason'), 'council_user_id' => $case->getAttribute('council_user_id'), 'council_reason' => $case->getAttribute('council_reason'), 'closed_reason' => $case->getAttribute('closed_reason'), 'closed_at' => $case->closed_at?->toIso8601String()];
    }

    /** @param Builder<TerminationCase> $query */
    private function list(Request $request, Builder $query): JsonResponse
    {
        $collection = $this->collection($request);
        $this->apply($query, $collection);
        $page = $query->paginate($collection->perPage, page: $collection->page);

        return response()->json([
            'data' => $page->getCollection()->map(fn (TerminationCase $case): array => $this->resource($case)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** @param Builder<TerminationCase> $query */
    private function export(Request $request, Builder $query, AuditLogger $audit, XlsxExporter $xlsx, string $filename): BinaryFileResponse
    {
        $collection = $this->collection($request);
        $this->apply($query, $collection);
        $rows = (function () use ($query): \Generator {
            foreach ($query->lazyById(500) as $case) {
                yield [
                    $case->id, $case->student_id, $case->state, $case->advisor_user_id, $case->advisor_recommendation,
                    $case->department_head_user_id, $case->council_user_id, $case->closed_reason, $case->closed_at?->toIso8601String(),
                ];
            }
        })();
        $count = (clone $query)->count();
        $response = $xlsx->download($filename, ['Case ID', 'Student ID', 'State', 'Advisor ID', 'Advisor recommendation', 'Department Head ID', 'Council ID', 'Closed reason', 'Closed at'], $rows, app()->getLocale() === 'ar');
        $audit->record('termination.cases.exported', (int) $request->user()?->getAuthIdentifier(), ['criteria' => $collection->auditCriteria(), 'row_count' => $count], 'termination_cases');

        return $response;
    }

    private function collection(Request $request): CollectionQuery
    {
        return CollectionQuery::from($request->query(), knownFilters: ['student_id', 'state', 'include_decided'], readableFilters: ['student_id', 'state'], sortable: ['id', 'student_id', 'state', 'created_at', 'closed_at'], integerFilters: ['student_id']);
    }

    /** @param Builder<TerminationCase> $query */
    private function apply(Builder $query, CollectionQuery $collection): void
    {
        foreach ($collection->filters as $field => $values) {
            $query->whereIn($field, $values);
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
    }

    private function notifyTransition(Notifier $notifier, int $studentUserId, int $advisorUserId, TerminationCase $case, string $change): void
    {
        $payload = ['case_id' => $case->id, 'state' => $case->state, 'change' => $change];
        foreach (array_unique([$studentUserId, $advisorUserId]) as $recipient) {
            $notifier->notifyOnce($recipient, NotificationType::TerminationCaseChanged, "termination-case:{$case->id}:{$change}:{$recipient}", $payload);
        }
    }
}
