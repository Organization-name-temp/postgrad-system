<?php

namespace Modules\AcademicStanding\Public;

final readonly class StandingSummary
{
    public function __construct(
        public int $studentId,
        public AcademicStanding $standing,
        public ?float $cgpa,
        public string $explanation,
        public ?int $registrationCeilingCh,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'standing' => $this->standing->value,
            'cgpa' => $this->cgpa,
            'explanation' => $this->explanation,
            'registration_ceiling_ch' => $this->registrationCeilingCh,
        ];
    }
}
