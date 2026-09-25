<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\ScheduleChangeController;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;

/**
 * The acting-Student helpers shared by the self-serve registration surfaces — the cart
 * flow ({@see RegistrationController}) and the period-gated schedule changes
 * ({@see ScheduleChangeController}) — so "who is registering, and what do we call them?"
 * is answered once (ADR-0012: every cross-module fact reads through Identity\Public).
 */
trait ResolvesStudent
{
    /**
     * The acting Student's anchor, or a 403 when the acting user holds no Student record —
     * read through the Identity\Public {@see StudentDirectory}.
     */
    protected function actingStudent(Request $request, StudentDirectory $students): StudentSummary
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $student = $students->find($userId);
        abort_if($student === null, 403, 'You do not have a student record to register with.');
        abort_if($student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('credit_decisions.registration.student_inactive'));

        return $student;
    }

    /**
     * The Student's display name for a notification line — resolved through the Identity
     * {@see Directory} (the Student anchor summary carries no name, ADR-0012). Falls back to
     * the University ID when the owning user cannot be resolved.
     */
    protected function studentDisplayName(StudentSummary $student, Directory $directory): string
    {
        $users = $directory->findMany([$student->userId]);

        return isset($users[$student->userId]) ? $users[$student->userId]->name : $student->universityId;
    }
}
