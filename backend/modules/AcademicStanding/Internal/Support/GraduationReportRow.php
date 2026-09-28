<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\GraduationReadiness;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Identity\Public\StudentSummary;

final readonly class GraduationReportRow
{
    public function __construct(public StudentSummary $student, public ?ProgramSummary $program, public ?GraduationReadiness $readiness) {}

    public function group(): string
    {
        return $this->readiness === null ? 'unavailable' : ($this->readiness->ready ? 'ready' : 'not_ready');
    }

    public function sortValue(string $field): int|float|string|null
    {
        return match ($field) {
            'student_id' => $this->student->studentId,
            'name_en' => $this->student->identity?->nameEn,
            'name_ar' => $this->student->identity?->nameAr,
            'university_id' => $this->student->universityId,
            'program_id' => $this->student->programId,
            'lifecycle' => $this->student->lifecycleStatus->value,
            'readiness' => $this->group(),
            'remaining_ch' => $this->readiness?->remainingCreditHours,
            'cgpa' => $this->readiness?->cgpa,
            default => throw new \LogicException('Unsupported Standing Report sort field.'),
        };
    }

    /** @return list<string> */
    public function unavailable(): array
    {
        return array_values(array_filter([
            $this->readiness === null ? 'curriculum' : null,
            $this->student->identity === null ? 'student_name' : null,
            $this->program === null ? 'program' : null,
        ]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'student_id' => $this->student->studentId,
            'university_id' => $this->student->universityId,
            'name_en' => $this->student->identity?->nameEn,
            'name_ar' => $this->student->identity?->nameAr,
            'program' => $this->program === null ? null : [
                'id' => $this->program->id, 'code' => $this->program->code,
                'name_en' => $this->program->nameEn, 'name_ar' => $this->program->nameAr,
                'degree_level' => $this->program->degreeLevel->value,
                'department_id' => $this->program->departmentId, 'faculty_id' => $this->program->facultyId,
            ],
            'lifecycle' => $this->student->lifecycleStatus->value,
            'readiness' => $this->readiness === null ? null : [
                'ready' => $this->readiness->ready,
                'remaining_credit_hours' => $this->readiness->remainingCreditHours,
                'cgpa' => $this->readiness->cgpa,
                'approaching_graduation' => $this->readiness->approachingGraduation,
                'blockers' => array_column($this->readiness->blockers, 'value'),
            ],
            'readiness_group' => $this->group(),
            'unavailable' => $this->unavailable(),
        ];
    }
}
