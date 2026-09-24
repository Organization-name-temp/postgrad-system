<?php

namespace Modules\AcademicStanding\Public;

final readonly class GraduationReadiness
{
    /** @param list<GraduationBlocker> $blockers */
    public function __construct(
        public bool $ready,
        public int $remainingCreditHours,
        public ?float $cgpa,
        public bool $approachingGraduation,
        public array $blockers,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'remaining_credit_hours' => $this->remainingCreditHours,
            'cgpa' => $this->cgpa,
            'approaching_graduation' => $this->approachingGraduation,
            'blockers' => array_map(fn (GraduationBlocker $blocker): string => $blocker->value, $this->blockers),
        ];
    }
}
