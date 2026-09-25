<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\CapacityOverrideFinalizer;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;

/** @group Enrollment */
final class CapacityOverrideController
{
    /** @response 200 {"data":[{"id":1,"registration_request_id":2,"course_offering_id":3,"course_id":4,"department_id":5,"state":"Pending","reason":null}]} */
    public function index(Request $request, DepartmentHeadAssignments $heads): JsonResponse
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $items = CapacityOverrideRequirement::query()->where('state', 'Pending')->orderBy('id')->get()
            ->filter(fn (CapacityOverrideRequirement $item): bool => in_array($userId, $heads->headsForDepartment($item->department_id), true))
            ->values()->map(fn (CapacityOverrideRequirement $item): array => $this->resource($item));

        return response()->json(['data' => $items]);
    }

    /**
     * Decide one scoped full-Offering requirement.
     *
     * @bodyParam action string required approve or deny. Example: approve
     * @bodyParam reason string required The accountable reason. Example: Required retake must proceed.
     *
     * @response 200 {"data":{"id":1,"state":"Approved","reason":"Required retake must proceed."}}
     */
    public function decide(
        Request $request,
        CapacityOverrideRequirement $requirement,
        DepartmentHeadAssignments $heads,
        AuditLogger $audit,
        CapacityOverrideFinalizer $finalizer,
        StudentGuard $guard,
    ): JsonResponse {
        $actor = (int) $request->user()?->getAuthIdentifier();
        abort_unless(in_array($actor, $heads->headsForDepartment($requirement->department_id), true), 404);
        $validated = $request->validate(['action' => ['required', Rule::in(['approve', 'deny'])], 'reason' => ['required', 'string', 'max:2000']]);
        abort_if($requirement->state !== 'Pending', 409, __('credit_decisions.registration.capacity_decided'));

        $registrationSnapshot = RegistrationRequest::query()->findOrFail($requirement->registration_request_id);
        DB::transaction(function () use ($requirement, $registrationSnapshot, $validated, $actor, $audit, $finalizer, $guard): void {
            $student = $guard->lock((int) $registrationSnapshot->student_id);
            abort_if($student === null || $student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('credit_decisions.registration.student_inactive'));
            $registration = RegistrationRequest::query()->lockForUpdate()->findOrFail($registrationSnapshot->id);
            abort_if($registration->status !== RegistrationStatus::AwaitingCapacityOverride, 409, __('credit_decisions.registration.request_not_awaiting_capacity'));
            $locked = CapacityOverrideRequirement::query()->lockForUpdate()->findOrFail($requirement->id);
            abort_if($locked->state !== 'Pending', 409, __('credit_decisions.registration.capacity_decided'));
            $state = $validated['action'] === 'approve' ? 'Approved' : 'Denied';
            $locked->update(['state' => $state, 'decided_by' => $actor, 'reason' => $validated['reason'], 'decided_at' => now()]);
            if ($state === 'Denied') {
                $registration->update(['status' => RegistrationStatus::Rejected]);
            } elseif (! CapacityOverrideRequirement::query()->where('registration_request_id', $registration->id)->where('state', 'Pending')->exists()) {
                $finalizer->finalizeIfReady($registration);
            }
            $audit->record('registration.capacity_override_decided', $actor, ['state' => $state, 'reason' => $validated['reason']], 'capacity_override_requirement', (string) $locked->id);
        });

        return response()->json(['data' => $this->resource($requirement->fresh())]);
    }

    /** @return array<string, mixed> */
    private function resource(CapacityOverrideRequirement $item): array
    {
        return ['id' => $item->id, 'registration_request_id' => $item->registration_request_id, 'course_offering_id' => $item->course_offering_id, 'course_id' => $item->course_id, 'department_id' => $item->department_id, 'state' => $item->state, 'reason' => $item->getAttribute('reason')];
    }
}
