<?php

namespace Modules\AcademicStanding\Public;

final readonly class TerminationCaseSummary
{
    /** @param array<string, mixed> $evidence */
    public function __construct(public int $id, public int $studentId, public string $fingerprint, public string $state, public array $evidence) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'student_id' => $this->studentId, 'fingerprint' => $this->fingerprint, 'state' => $this->state, 'evidence' => $this->evidence];
    }
}
