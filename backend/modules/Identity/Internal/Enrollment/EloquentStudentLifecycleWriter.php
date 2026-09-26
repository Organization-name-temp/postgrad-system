<?php

namespace Modules\Identity\Internal\Enrollment;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentLifecycleWriter;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;

final readonly class EloquentStudentLifecycleWriter implements StudentLifecycleWriter
{
    public function __construct(private StudentSummaryMapper $summaries) {}

    public function terminate(int $studentId): ?StudentSummary
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('The Student lifecycle writer requires an active transaction.');
        }

        $student = Student::query()->lockForUpdate()->find($studentId);
        if ($student === null) {
            return null;
        }
        if ($student->lifecycle_status !== StudentLifecycleStatus::Terminated) {
            $student->forceFill(['lifecycle_status' => StudentLifecycleStatus::Terminated])->save();
        }

        return $this->summaries->fromModel($student);
    }
}
