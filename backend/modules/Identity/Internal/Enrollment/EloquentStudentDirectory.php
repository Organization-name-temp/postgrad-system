<?php

namespace Modules\Identity\Internal\Enrollment;

use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * The Eloquent-backed {@see StudentDirectory}. Reads the `students` table and maps each
 * row to a {@see StudentSummary}, so Identity's model never crosses the seam (ADR-0012).
 * Bound to the interface in IdentityServiceProvider.
 */
class EloquentStudentDirectory implements StudentDirectory
{
    public function find(int $userId): ?StudentSummary
    {
        return $this->toSummary(Student::query()->where('user_id', $userId)->first());
    }

    public function findByUniversityId(string $universityId): ?StudentSummary
    {
        return $this->toSummary(Student::query()->where('university_id', $universityId)->first());
    }

    private function toSummary(?Student $student): ?StudentSummary
    {
        if ($student === null) {
            return null;
        }

        return new StudentSummary(
            studentId: (int) $student->getKey(),
            userId: $student->user_id,
            universityId: $student->university_id,
            programId: $student->program_id,
            enrollmentTerm: $student->enrollment_term,
            admissionCycleId: $student->admission_cycle_id,
            advisorId: $student->advisor_id,
            admittedAt: $student->admitted_at,
        );
    }
}
