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
    public function __construct(private readonly StudentSummaryMapper $summaries) {}

    public function find(int $userId): ?StudentSummary
    {
        return $this->toSummary(Student::query()->where('user_id', $userId)->first());
    }

    public function findByUniversityId(string $universityId): ?StudentSummary
    {
        return $this->toSummary(Student::query()->where('university_id', $universityId)->first());
    }

    public function findByStudentId(int $studentId): ?StudentSummary
    {
        return $this->toSummary(Student::query()->whereKey($studentId)->first());
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, StudentSummary>
     */
    public function findManyByStudentId(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return Student::query()
            ->whereIn('id', $studentIds)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [
                (int) $student->getKey() => $this->toSummary($student),
            ])
            ->all();
    }

    /** @return list<int> */
    public function studentIdsAdvisedBy(int $advisorUserId): array
    {
        return Student::query()
            ->where('advisor_id', $advisorUserId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function allStudentIds(): array
    {
        return Student::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function toSummary(?Student $student): ?StudentSummary
    {
        return $this->summaries->fromModel($student);
    }
}
