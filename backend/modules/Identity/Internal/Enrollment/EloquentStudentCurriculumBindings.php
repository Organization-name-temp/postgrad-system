<?php

namespace Modules\Identity\Internal\Enrollment;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentCurriculumBindings;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;
use Modules\Identity\Public\StudentCurriculumBindingCandidate;
use RuntimeException;

class EloquentStudentCurriculumBindings implements StudentCurriculumBindings
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function backfillCandidates(): array
    {
        return Student::query()
            ->whereNull('curriculum_version_id')
            ->orderBy('id')
            ->get(['id', 'program_id', 'admitted_at'])
            ->map(static fn (Student $student): StudentCurriculumBindingCandidate => new StudentCurriculumBindingCandidate(
                studentId: (int) $student->getKey(),
                programId: $student->program_id,
                admittedAt: CarbonImmutable::instance($student->admitted_at),
            ))
            ->all();
    }

    public function bindBackfill(
        int $studentId,
        int $curriculumVersionId,
        CurriculumVersionBindingSource $source,
        CarbonInterface $versionChangedAt,
    ): void {
        $student = Student::query()
            ->whereKey($studentId)
            ->whereNull('curriculum_version_id')
            ->first();

        if ($student === null) {
            throw new RuntimeException("Student {$studentId} is not awaiting Curriculum Version backfill.");
        }

        $student->forceFill([
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_version_binding_source' => $source,
        ])->save();

        if ($source !== CurriculumVersionBindingSource::EarliestAvailableFallback) {
            return;
        }

        $this->audit->record(
            action: 'student.curriculum_version_fallback_bound',
            metadata: [
                'program_id' => $student->program_id,
                'curriculum_version_id' => $curriculumVersionId,
                'admitted_at' => $student->admitted_at->toISOString(),
                'version_changed_at' => $versionChangedAt->toISOString(),
                'binding_source' => $source->value,
            ],
            subjectType: 'student',
            subjectId: (string) $student->getKey(),
        );
    }
}
