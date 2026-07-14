<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\Application;

/**
 * An Application in the { data: ... } envelope. Registration (ticket 03) returns
 * the freshly-created Draft so the SPA can route the new applicant into their
 * wizard. It carries only the Application's identity for now — the per-step
 * personal-info, documents, program selection, and status timeline are added to
 * this resource by the wizard/workflow tickets that introduce them.
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        return [
            'id' => $application->getKey(),
            'status' => $application->status->value,
            'admission_cycle_id' => $application->admission_cycle_id,
        ];
    }
}
