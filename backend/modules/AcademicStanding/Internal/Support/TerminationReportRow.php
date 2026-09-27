<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Identity\Public\StudentSummary;

final readonly class TerminationReportRow
{
    public function __construct(public StudentSummary $student, public ?ProgramSummary $program, public TerminationCase $case) {}

    public function sortValue(string $field): int|string|null
    {
        return match ($field) {
            'student_id' => $this->student->studentId,
            'name_en' => $this->student->identity?->nameEn,
            'name_ar' => $this->student->identity?->nameAr,
            'university_id' => $this->student->universityId,
            'program_id' => $this->student->programId,
            'lifecycle' => $this->student->lifecycleStatus->value,
            'case_id' => $this->case->id,
            'state' => $this->case->state,
            'created_at' => $this->case->created_at?->toIso8601String(),
            'closed_at' => $this->case->closed_at?->toIso8601String(),
            default => throw new \LogicException('Unsupported Termination Report sort field.'),
        };
    }

    /** @return list<string> */
    public function unavailable(): array
    {
        return array_values(array_filter([
            $this->student->identity === null ? 'student_name' : null,
            $this->program === null ? 'program' : null,
        ]));
    }

    /** @return array<string, mixed> */
    public function toArray(?string $reviewUrl = null): array
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
            'case_id' => $this->case->id,
            'state' => $this->case->state,
            'created_at' => $this->case->created_at?->toIso8601String(),
            'advisor_reviewed_at' => $this->case->advisor_reviewed_at?->toIso8601String(),
            'department_head_reviewed_at' => $this->case->department_head_reviewed_at?->toIso8601String(),
            'council_decided_at' => $this->case->council_decided_at?->toIso8601String(),
            'closed_at' => $this->case->closed_at?->toIso8601String(),
            'unavailable' => $this->unavailable(),
            'review_url' => $reviewUrl,
        ];
    }
}
