<?php

namespace Modules\Catalog\Internal\Enums;

use Modules\Catalog\Internal\Models\CatalogVersion;

/**
 * The kinds of catalog entity that carry version history (ADR-0011) — the
 * `subject_type` of a {@see CatalogVersion}. A
 * closed set in code, in the same style as the other Catalog enums; Catalog-owned
 * and Internal.
 */
enum VersionSubject: string
{
    case Program = 'program';
    case Course = 'course';
}
