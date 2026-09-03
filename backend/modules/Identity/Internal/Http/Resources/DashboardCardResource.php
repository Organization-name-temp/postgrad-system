<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCardResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $meta
     */
    public function __construct(array $resource, array $meta = [])
    {
        parent::__construct($resource);
        $this->additional(['meta' => (object) $meta]);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
