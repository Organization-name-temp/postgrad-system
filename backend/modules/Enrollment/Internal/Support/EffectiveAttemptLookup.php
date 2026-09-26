<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Models\Enrollment;

/** Resolves the current for-credit Attempt outcome for one Student/Course target. */
final class EffectiveAttemptLookup
{
    public function hasFailed(int $studentId, int $courseId): bool
    {
        return $this->latestCompleted($studentId, $courseId)?->earned === false;
    }

    public function hasCompleted(int $studentId, int $courseId): bool
    {
        return $this->latestCompleted($studentId, $courseId) !== null;
    }

    private function latestCompleted(int $studentId, int $courseId): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $studentId)
            ->whereNotNull('earned')
            ->whereHas('offering', fn ($query) => $query->where('course_id', $courseId))
            ->orderByDesc('id')
            ->get()
            ->first(fn (Enrollment $enrollment): bool => $enrollment->course_status->burnsRetakeAllowance());
    }
}
