<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Public\UserSummary;

/** @mixin UserSummary */
class DirectoryUserResource extends JsonResource
{
    /** @return array{id: int, name: string, email: string} */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
