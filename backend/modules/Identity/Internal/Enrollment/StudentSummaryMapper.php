<?php

namespace Modules\Identity\Internal\Enrollment;

use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\AdmissionEvidenceSnapshot;
use Modules\Identity\Public\StudentIdentity;
use Modules\Identity\Public\StudentSummary;

/** The single Identity-internal mapper from the owned model to its Public read DTO. */
final class StudentSummaryMapper
{
    public function fromModel(?Student $student): ?StudentSummary
    {
        if ($student === null) {
            return null;
        }

        return new StudentSummary(
            studentId: (int) $student->getKey(),
            userId: $student->user_id,
            universityId: $student->university_id,
            programId: $student->program_id,
            curriculumVersionId: $student->curriculum_version_id,
            curriculumVersionBindingSource: $student->curriculum_version_binding_source,
            enrollmentTerm: $student->enrollment_term,
            admissionCycleId: $student->admission_cycle_id,
            advisorId: $student->advisor_id,
            admittedAt: $student->admitted_at,
            lifecycleStatus: $student->lifecycle_status,
            identity: new StudentIdentity(
                nameEn: $student->name_en,
                nameAr: $student->name_ar,
                nationalId: $student->national_id,
                dateOfBirth: $student->date_of_birth->format('Y-m-d'),
                nationality: $student->nationality,
                gender: $student->gender,
                identificationType: $student->identification_type,
            ),
            admissionEvidence: $student->admission_evidence === null
                ? null
                : AdmissionEvidenceSnapshot::fromArray($student->admission_evidence),
        );
    }
}
