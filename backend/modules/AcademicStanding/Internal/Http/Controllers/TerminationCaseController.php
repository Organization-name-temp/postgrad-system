<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

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

/** @group Academic Standing */
final class TerminationCaseController
{
    /** @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingAdvisor","evidence":{"df_grades":[]},"advisor_user_id":null,"advisor_assessment":null,"advisor_recommendation":null,"department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}} */
    public function mine(Request $request, StudentDirectory $students, TerminationCases $cases): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return response()->json(['data' => $cases->currentForStudent($student->studentId)?->toArray()]);
    }

    /** @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingAdvisor","evidence":{"df_grades":[]},"advisor_user_id":null,"advisor_assessment":null,"advisor_recommendation":null,"department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}]} */
    public function advisorQueue(Request $request, StudentDirectory $students): JsonResponse
    {
        $ids = $students->studentIdsAdvisedBy((int) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => TerminationCase::query()->whereIn('student_id', $ids)->where('state', 'AwaitingAdvisor')->orderBy('id')->get()->map(fn (TerminationCase $case): array => $this->resource($case))]);
    }

    /** @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingDepartmentHead","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}]} */
    public function departmentQueue(Request $request, StudentDirectory $students, ProgramCatalog $programs, DepartmentHeadAssignments $heads): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $items = TerminationCase::query()->where('state', 'AwaitingDepartmentHead')->orderBy('id')->get()
            ->filter(fn (TerminationCase $case): bool => $this->isDepartmentHead($actor, $case->student_id, $students, $programs, $heads))
            ->values()->map(fn (TerminationCase $case): array => $this->resource($case));

        return response()->json(['data' => $items]);
    }

    /** @response 200 {"data":[{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingCouncil","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":null,"council_reason":null}]} */
    public function councilQueue(): JsonResponse
    {
        return response()->json(['data' => TerminationCase::query()->where('state', 'AwaitingCouncil')->orderBy('id')->get()->map(fn (TerminationCase $case): array => $this->resource($case))]);
    }

    /**
     * @bodyParam assessment string required Example: Two distinct failing grades remain effective.
     * @bodyParam recommendation string required continue or dismiss. Example: continue
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingDepartmentHead","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":null,"department_head_reason":null,"council_user_id":null,"council_reason":null}}
     */
    public function advisorReview(Request $request, TerminationCase $case, StudentDirectory $students, TerminationCases $cases, AuditLogger $audit): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $student = $students->findByStudentId($case->student_id);
        abort_unless($student !== null && $student->advisorId === $actor, 404);
        $validated = $request->validate(['assessment' => ['required', 'string', 'max:4000'], 'recommendation' => ['required', Rule::in(['continue', 'dismiss'])]]);
        $current = $cases->reconcile($case->student_id);
        abort_if($current === null || $current->id !== $case->id || $case->fresh()->state !== 'AwaitingAdvisor', 409, __('academic_standing.evidence_changed'));
        $case->update(['state' => 'AwaitingDepartmentHead', 'advisor_user_id' => $actor, 'advisor_assessment' => $validated['assessment'], 'advisor_recommendation' => $validated['recommendation'], 'advisor_reviewed_at' => now()]);
        $audit->record('termination.advisor_reviewed', $actor, $validated, 'termination_case', (string) $case->id);

        return response()->json(['data' => $this->resource($case->fresh())]);
    }

    /**
     * @bodyParam action string required recommend or dismiss. Example: recommend
     * @bodyParam reason string required Example: Evidence is actionable.
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"AwaitingCouncil","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":null,"council_reason":null}}
     */
    public function departmentReview(Request $request, TerminationCase $case, StudentDirectory $students, ProgramCatalog $programs, DepartmentHeadAssignments $heads, TerminationCases $cases, StandingReconciler $standing, AuditLogger $audit): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        abort_unless($this->isDepartmentHead($actor, $case->student_id, $students, $programs, $heads), 404);
        $validated = $request->validate(['action' => ['required', Rule::in(['recommend', 'dismiss'])], 'reason' => ['required', 'string', 'max:4000']]);
        $current = $cases->reconcile($case->student_id);
        abort_if($current === null || $current->id !== $case->id || $case->fresh()->state !== 'AwaitingDepartmentHead', 409, __('academic_standing.evidence_changed'));
        $state = $validated['action'] === 'recommend' ? 'AwaitingCouncil' : 'Dismissed';
        $case->update(['state' => $state, 'department_head_user_id' => $actor, 'department_head_reason' => $validated['reason'], 'department_head_reviewed_at' => now(), 'closed_reason' => $state === 'Dismissed' ? 'Dismissed' : null, 'closed_at' => $state === 'Dismissed' ? now() : null]);
        if ($state === 'Dismissed') {
            $standing->reconcile($case->student_id, 'termination_case', $case->id);
        }
        $audit->record('termination.department_reviewed', $actor, ['state' => $state, 'reason' => $validated['reason']], 'termination_case', (string) $case->id);

        return response()->json(['data' => $this->resource($case->fresh())]);
    }

    /**
     * @bodyParam action string required terminate or dismiss. Example: terminate
     * @bodyParam reason string required Example: Council confirmed termination.
     *
     * @response 200 {"data":{"id":1,"student_id":5,"fingerprint":"abc","state":"Terminated","evidence":{"df_grades":[]},"advisor_user_id":3,"advisor_assessment":"Two failures.","advisor_recommendation":"continue","department_head_user_id":6,"department_head_reason":"Actionable.","council_user_id":8,"council_reason":"Confirmed."}}
     */
    public function councilDecision(Request $request, TerminationCase $case, StudentGuard $guard, StudentLifecycleWriter $lifecycle, AcademicRequestCloseout $closeout, TerminationCases $cases, StandingReconciler $standing, AuditLogger $audit): JsonResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $validated = $request->validate(['action' => ['required', Rule::in(['terminate', 'dismiss'])], 'reason' => ['required', 'string', 'max:4000']]);
        DB::transaction(function () use ($case, $actor, $validated, $guard, $lifecycle, $closeout, $cases): void {
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
        });
        $standing->reconcile($case->student_id, 'termination_case', $case->id);
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
        return ['id' => $case->id, 'student_id' => $case->student_id, 'fingerprint' => $case->fingerprint, 'state' => $case->state, 'evidence' => $case->evidence, 'advisor_user_id' => $case->getAttribute('advisor_user_id'), 'advisor_assessment' => $case->getAttribute('advisor_assessment'), 'advisor_recommendation' => $case->getAttribute('advisor_recommendation'), 'department_head_user_id' => $case->getAttribute('department_head_user_id'), 'department_head_reason' => $case->getAttribute('department_head_reason'), 'council_user_id' => $case->getAttribute('council_user_id'), 'council_reason' => $case->getAttribute('council_reason')];
    }
}
