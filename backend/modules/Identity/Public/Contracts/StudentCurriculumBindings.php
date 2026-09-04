<?php

namespace Modules\Identity\Public\Contracts;

use Carbon\CarbonInterface;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;
use Modules\Identity\Public\StudentCurriculumBindingCandidate;

/**
 * The narrow Identity-owned seam used by the application migration that composes
 * historical Student anchors with Catalog's Public version reader.
 */
interface StudentCurriculumBindings
{
    /** @return list<StudentCurriculumBindingCandidate> */
    public function backfillCandidates(): array;

    /** Bind only a currently-unbound historical Student and audit fallback approximations. */
    public function bindBackfill(
        int $studentId,
        int $curriculumVersionId,
        CurriculumVersionBindingSource $source,
        CarbonInterface $versionChangedAt,
    ): void;
}
