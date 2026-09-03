<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCardResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $meta
     */
    public function __construct(?array $resource, array $meta = [])
    {
        parent::__construct($resource);
        $this->additional(['meta' => (object) $meta]);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource ?? [];
    }

    public function toResponse($request): JsonResponse
    {
        return $this->resource === null
            ? response()->json(['data' => null, ...$this->additional])
            : parent::toResponse($request);
    }
}
