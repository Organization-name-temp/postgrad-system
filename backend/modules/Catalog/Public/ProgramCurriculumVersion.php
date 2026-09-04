<?php

namespace Modules\Catalog\Public;

use Carbon\CarbonImmutable;

/**
 * The opaque identity of one historical Program aggregate snapshot. Consumers use
 * this DTO to bind academic records to Catalog history without receiving the
 * CatalogVersion model or depending on Catalog's persistence details.
 */
final readonly class ProgramCurriculumVersion
{
    public function __construct(
        public int $id,
        public int $programId,
        public int $versionNo,
        public CarbonImmutable $changedAt,
    ) {}
}
