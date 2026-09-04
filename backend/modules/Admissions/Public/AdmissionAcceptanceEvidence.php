<?php

namespace Modules\Admissions\Public;

/**
 * The immutable admission-time facts Enrollment may use to evaluate automatic
 * Preparatory Exemptions (ADR-0032). Values are primitives and opaque ids so no
 * Admissions model or Internal enum crosses the module seam.
 */
final readonly class AdmissionAcceptanceEvidence
{
    public function __construct(
        public int $studentId,
        public int $userId,
        public int $applicationId,
        public int $admittedProgramId,
        public string $admittedProgramDegreeLevel,
        public int $admittedDepartmentId,
        public string $previousDegree,
        public bool $isEjustGraduate,
        public ?int $previousEjustProgramId,
        public ?string $previousEjustProgramDegreeLevel,
        public ?int $previousEjustDepartmentId,
        public string $englishTest,
        public ?float $englishScore,
    ) {}
}
