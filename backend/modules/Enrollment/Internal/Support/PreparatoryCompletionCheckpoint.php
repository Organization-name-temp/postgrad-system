<?php

namespace Modules\Enrollment\Internal\Support;

use Carbon\CarbonInterface;
use Modules\Calendar\Public\AcademicCalendar;

final readonly class PreparatoryCompletionCheckpoint
{
    public function __construct(private AcademicCalendar $calendar) {}

    public function deadline(string $enrollmentTerm): ?CarbonInterface
    {
        $first = $this->calendar->findByNameEn($enrollmentTerm);
        $second = $first === null ? null : $this->calendar->nextAfter($first->id);

        return $second?->gradeSubmissionDeadlineAt;
    }

    public function hasPassed(string $enrollmentTerm): bool
    {
        $deadline = $this->deadline($enrollmentTerm);

        return $deadline !== null && now()->greaterThanOrEqualTo($deadline);
    }
}
