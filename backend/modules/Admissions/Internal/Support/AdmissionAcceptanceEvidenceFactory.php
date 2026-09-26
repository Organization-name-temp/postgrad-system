<?php

namespace Modules\Admissions\Internal\Support;

use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Public\AdmissionAcceptanceEvidence;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Identity\Public\AdmissionEvidenceSnapshot;

/** Builds the one immutable acceptance snapshot used by the event and recovery anchor. */
final class AdmissionAcceptanceEvidenceFactory
{
    public function __construct(private readonly ProgramCatalog $programs) {}

    public function facts(Application $application, ProgramSummary $admittedProgram): AdmissionEvidenceSnapshot
    {
        $previous = $application->previous_ejust_program_id === null
            ? null
            : $this->programs->find((int) $application->previous_ejust_program_id);

        return new AdmissionEvidenceSnapshot(
            userId: (int) $application->user_id,
            applicationId: (int) $application->getKey(),
            admittedProgramId: $admittedProgram->id,
            admittedProgramDegreeLevel: $admittedProgram->degreeLevel->value,
            admittedDepartmentId: $admittedProgram->departmentId,
            previousDegree: $application->previous_degree->value,
            isEjustGraduate: (bool) $application->is_ejust_graduate,
            previousEjustProgramId: $application->previous_ejust_program_id,
            previousEjustProgramDegreeLevel: $previous?->degreeLevel->value,
            previousEjustDepartmentId: $previous?->departmentId,
            englishTest: $application->english_test->value,
            englishScore: $application->english_score === null ? null : (float) $application->english_score,
        );
    }

    public function fromFacts(int $studentId, AdmissionEvidenceSnapshot $facts): AdmissionAcceptanceEvidence
    {
        return new AdmissionAcceptanceEvidence(
            studentId: $studentId,
            userId: $facts->userId,
            applicationId: $facts->applicationId,
            admittedProgramId: $facts->admittedProgramId,
            admittedProgramDegreeLevel: $facts->admittedProgramDegreeLevel,
            admittedDepartmentId: $facts->admittedDepartmentId,
            previousDegree: $facts->previousDegree,
            isEjustGraduate: $facts->isEjustGraduate,
            previousEjustProgramId: $facts->previousEjustProgramId,
            previousEjustProgramDegreeLevel: $facts->previousEjustProgramDegreeLevel,
            previousEjustDepartmentId: $facts->previousEjustDepartmentId,
            englishTest: $facts->englishTest,
            englishScore: $facts->englishScore,
        );
    }
}
