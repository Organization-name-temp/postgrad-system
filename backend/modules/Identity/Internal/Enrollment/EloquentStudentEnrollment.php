<?php

namespace Modules\Identity\Internal\Enrollment;

use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\AdmittedStudent;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;

/**
 * The Eloquent-backed {@see StudentEnrollment} — the concrete acceptance mint bound in
 * IdentityServiceProvider. It performs the whole hand-off (ADR-0013) but opens **no
 * transaction of its own**: Admissions calls {@see self::admit()} inside its accept
 * transaction, so the Student, the role swap, and the audit entry commit or roll back
 * together with the Application's `Accepted` status.
 */
class EloquentStudentEnrollment implements StudentEnrollment
{
    public function __construct(
        private readonly UniversityIdGenerator $universityIds,
        private readonly AuditLogger $audit,
    ) {}

    public function admit(AdmitStudent $command): AdmittedStudent
    {
        $universityId = $this->universityIds->next(
            $command->admissionYear,
            $command->programCode,
            $command->programId,
        );

        $student = Student::create([
            'user_id' => $command->userId,
            'university_id' => $universityId,
            'program_id' => $command->programId,
            'curriculum_version_id' => $command->curriculumVersionId,
            'curriculum_version_binding_source' => CurriculumVersionBindingSource::AdmissionTime,
            'enrollment_term' => $command->enrollmentTerm,
            'admission_cycle_id' => $command->admissionCycleId,
            'advisor_id' => $command->advisorId,
            'name_en' => $command->identity->nameEn,
            'name_ar' => $command->identity->nameAr,
            'national_id' => $command->identity->nationalId,
            'identification_type' => $command->identity->identificationType,
            'date_of_birth' => $command->identity->dateOfBirth,
            'nationality' => $command->identity->nationality,
            'gender' => $command->identity->gender,
            'admission_evidence' => $command->admissionEvidence?->toArray(),
            'admitted_at' => $command->admittedAt,
        ]);

        // Replace the Applicant role with Student (R01 → R02): the accepted user is no
        // longer applying, they are enrolled. The role swap is the authoritative change
        // — /me and every API gate read the new role.
        $user = User::query()->findOrFail($command->userId);
        $user->assignRole(Role::Student);
        $user->removeRole(Role::Applicant);

        $this->audit->record(
            action: 'student.admitted',
            actorUserId: $command->actorUserId,
            metadata: [
                'user_id' => $command->userId,
                'university_id' => $universityId,
                'program_id' => $command->programId,
                'curriculum_version_id' => $command->curriculumVersionId,
                'curriculum_version_binding_source' => CurriculumVersionBindingSource::AdmissionTime->value,
                'advisor_id' => $command->advisorId,
            ],
            subjectType: 'student',
            subjectId: (string) $student->getKey(),
        );

        return new AdmittedStudent(
            studentId: (int) $student->getKey(),
            userId: $command->userId,
            universityId: $universityId,
        );
    }

    public function suggestAdvisor(): ?UserSummary
    {
        $advisors = User::query()
            ->whereHas('userRoles', fn ($query) => $query->where('role', Role::Advisor->value))
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        // Live advisee counts keyed by advisor id; an advisor with none is simply
        // absent (load 0). Derived on demand — no stored cursor (spec §Advisor assignment).
        $loads = Student::query()
            ->selectRaw('advisor_id, count(*) as advisee_count')
            ->groupBy('advisor_id')
            ->pluck('advisee_count', 'advisor_id');

        // Fewest advisees wins; ties break on ascending id (advisors is ordered by id,
        // and strict `<` keeps the first — lowest-id — advisor at each load level).
        $leastLoaded = null;
        $leastLoad = PHP_INT_MAX;

        foreach ($advisors as $advisor) {
            $load = (int) ($loads[$advisor->getKey()] ?? 0);
            if ($load < $leastLoad) {
                $leastLoad = $load;
                $leastLoaded = $advisor;
            }
        }

        if ($leastLoaded === null) {
            return null;
        }

        return new UserSummary(
            id: (int) $leastLoaded->getKey(),
            name: (string) $leastLoaded->name,
            email: (string) $leastLoaded->email,
        );
    }
}
