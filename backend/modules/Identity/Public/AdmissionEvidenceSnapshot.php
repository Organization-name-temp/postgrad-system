<?php

namespace Modules\Identity\Public;

/**
 * Opaque-to-Identity admission facts frozen on the Student anchor for post-commit
 * recovery. Admissions constructs this value; Enrollment interprets it.
 */
final readonly class AdmissionEvidenceSnapshot
{
    public function __construct(
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

    /** @return array<string, bool|float|int|string|null> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'application_id' => $this->applicationId,
            'admitted_program_id' => $this->admittedProgramId,
            'admitted_program_degree_level' => $this->admittedProgramDegreeLevel,
            'admitted_department_id' => $this->admittedDepartmentId,
            'previous_degree' => $this->previousDegree,
            'is_ejust_graduate' => $this->isEjustGraduate,
            'previous_ejust_program_id' => $this->previousEjustProgramId,
            'previous_ejust_program_degree_level' => $this->previousEjustProgramDegreeLevel,
            'previous_ejust_department_id' => $this->previousEjustDepartmentId,
            'english_test' => $this->englishTest,
            'english_score' => $this->englishScore,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            applicationId: (int) $data['application_id'],
            admittedProgramId: (int) $data['admitted_program_id'],
            admittedProgramDegreeLevel: (string) $data['admitted_program_degree_level'],
            admittedDepartmentId: (int) $data['admitted_department_id'],
            previousDegree: (string) $data['previous_degree'],
            isEjustGraduate: (bool) $data['is_ejust_graduate'],
            previousEjustProgramId: isset($data['previous_ejust_program_id']) ? (int) $data['previous_ejust_program_id'] : null,
            previousEjustProgramDegreeLevel: isset($data['previous_ejust_program_degree_level']) ? (string) $data['previous_ejust_program_degree_level'] : null,
            previousEjustDepartmentId: isset($data['previous_ejust_department_id']) ? (int) $data['previous_ejust_department_id'] : null,
            englishTest: (string) $data['english_test'],
            englishScore: isset($data['english_score']) ? (float) $data['english_score'] : null,
        );
    }
}
