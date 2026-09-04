<?php

namespace Modules\Identity\Internal\Enrollment;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\StudentSummary;

final readonly class EloquentStudentGuard implements StudentGuard
{
    public function __construct(private StudentSummaryMapper $summaries) {}

    public function lock(int $studentId): ?StudentSummary
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('The Student guard requires an active transaction.');
        }

        return $this->summaries->fromModel(Student::query()->lockForUpdate()->find($studentId));
    }
}
