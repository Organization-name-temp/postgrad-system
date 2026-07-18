<?php

namespace Modules\Identity\Public;

use Illuminate\Support\Carbon;
use Modules\Identity\Public\Contracts\StudentDirectory;

/**
 * A read-only view of the `Student` anchor for consumers outside Identity
 * (ADR-0012/0013) — returned by {@see StudentDirectory}. The Eloquent Student model
 * never leaves `Identity\Internal`; a consumer gets exactly these fields.
 *
 * Its first use is `/me`: once a user holds the Student role (R02), the auth payload
 * embeds this summary so the newly-admitted user sees their University ID and student
 * identity on sign-in. The Program is named by opaque Catalog id (no FK, ADR-0012);
 * the SPA resolves its details through the Catalog tree if it needs to display them.
 * An immutable value object.
 */
final readonly class StudentSummary
{
    public function __construct(
        public int $studentId,
        public int $userId,
        public string $universityId,
        public int $programId,
        public string $enrollmentTerm,
        public int $admissionCycleId,
        public int $advisorId,
        public Carbon $admittedAt,
    ) {}

    /**
     * The public array shape for the `/me` payload — the single home for how a
     * Student anchor is exposed to the SPA.
     *
     * @return array{university_id: string, program_id: int, enrollment_term: string, admission_cycle_id: int, advisor_id: int, admitted_at: string}
     */
    public function toArray(): array
    {
        return [
            'university_id' => $this->universityId,
            'program_id' => $this->programId,
            'enrollment_term' => $this->enrollmentTerm,
            'admission_cycle_id' => $this->admissionCycleId,
            'advisor_id' => $this->advisorId,
            'admitted_at' => $this->admittedAt->toIso8601String(),
        ];
    }
}
