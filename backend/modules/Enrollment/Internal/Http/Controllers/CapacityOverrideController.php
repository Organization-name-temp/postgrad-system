<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;

/** @group Enrollment */
final class CapacityOverrideController
{
    /** @response 200 {"data":[]} */
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
        CourseCatalog $courses,
        AuditLogger $audit,
    ): JsonResponse {
        $actor = (int) $request->user()?->getAuthIdentifier();
        abort_unless(in_array($actor, $heads->headsForDepartment($requirement->department_id), true), 404);
        $validated = $request->validate(['action' => ['required', Rule::in(['approve', 'deny'])], 'reason' => ['required', 'string', 'max:2000']]);
        abort_if($requirement->state !== 'Pending', 409, 'This capacity requirement has already been decided.');

        DB::transaction(function () use ($requirement, $validated, $actor, $courses, $audit): void {
            $locked = CapacityOverrideRequirement::query()->lockForUpdate()->findOrFail($requirement->id);
            $state = $validated['action'] === 'approve' ? 'Approved' : 'Denied';
            $locked->update(['state' => $state, 'decided_by' => $actor, 'reason' => $validated['reason'], 'decided_at' => now()]);
            $registration = RegistrationRequest::query()->lockForUpdate()->findOrFail($locked->registration_request_id);
            if ($state === 'Denied') {
                $registration->update(['status' => RegistrationStatus::Rejected]);
            } elseif (! CapacityOverrideRequirement::query()->where('registration_request_id', $registration->id)->where('state', 'Pending')->exists()) {
                $registration->load('lines.offering');
                foreach ($registration->lines->where('decision', 'approved') as $line) {
                    $course = $courses->find((int) $line->offering->course_id);
                    if ($course === null || Enrollment::query()->where('student_id', $registration->student_id)->where('course_offering_id', $line->course_offering_id)->exists()) {
                        continue;
                    }
                    $obligation = MustRetakeObligation::query()->where('student_id', $registration->student_id)->where('course_id', $course->id)->where('state', 'Active')->first();
                    Enrollment::query()->create([
                        'student_id' => $registration->student_id,
                        'course_offering_id' => $line->course_offering_id,
                        'classification' => $obligation?->category === 'Preparatory' ? EnrollmentClassification::Preparatory : EnrollmentClassification::Core,
                        'course_status' => $line->mode === RegistrationMode::Audit ? CourseStatus::Audit : ($obligation === null ? CourseStatus::Regular : CourseStatus::Retake),
                        'credit_hours' => $course->totalCh,
                    ]);
                }
                $registration->update(['status' => RegistrationStatus::Approved]);
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
