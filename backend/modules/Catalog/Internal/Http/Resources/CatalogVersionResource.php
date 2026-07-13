<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\CatalogVersion;

/**
 * One entry in a Program's or Course's version history (ADR-0011). Wrapped in the
 * { data: ... } envelope. `snapshot` is the full aggregate as it stood at this
 * version; `change_note` is the action that produced it (created / updated /
 * activated / deactivated / curriculum_updated), which the SPA translates.
 * Read-only: there is no create/update/delete resource for versions.
 *
 * @mixin CatalogVersion
 */
class CatalogVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CatalogVersion $version */
        $version = $this->resource;

        return [
            'id' => $version->getKey(),
            'version_no' => $version->version_no,
            'change_note' => $version->change_note,
            'changed_by' => $version->changed_by,
            'changed_at' => $version->changed_at->toIso8601String(),
            'snapshot' => $version->snapshot,
        ];
    }
}
