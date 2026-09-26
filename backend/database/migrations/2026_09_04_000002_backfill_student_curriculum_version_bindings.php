<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\StudentCurriculumBindings;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;

return new class extends Migration
{
    /** Roll every binding back if any historical Student has no Program Version. */
    public $withinTransaction = true;

    public function up(): void
    {
        $catalog = app(ProgramCatalog::class);
        $bindings = app(StudentCurriculumBindings::class);

        foreach ($bindings->backfillCandidates() as $student) {
            $version = $catalog->curriculumVersionEffectiveAt($student->programId, $student->admittedAt);
            $source = CurriculumVersionBindingSource::AdmissionTime;

            if ($version === null) {
                $version = $catalog->earliestCurriculumVersion($student->programId);
                $source = CurriculumVersionBindingSource::EarliestAvailableFallback;
            }

            if ($version === null) {
                throw new RuntimeException(
                    "Student {$student->studentId} cannot be bound: Program {$student->programId} has no Curriculum Version.",
                );
            }

            $bindings->bindBackfill(
                studentId: $student->studentId,
                curriculumVersionId: $version->id,
                source: $source,
                versionChangedAt: $version->changedAt,
            );
        }
    }

    public function down(): void
    {
        // The surrounding Identity schema migration drops both binding columns.
    }
};
