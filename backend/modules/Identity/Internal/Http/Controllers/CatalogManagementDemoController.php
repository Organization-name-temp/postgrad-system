<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;

class CatalogManagementDemoController
{
    /**
     * Catalog management demo.
     *
     * A permission-gated sample endpoint (the Phase 0 RBAC tracer): reachable
     * only by users whose effective permissions include `catalog.manage`
     * (R06/R07 and admins). It proves API-side enforcement; later phases replace
     * it with the real Catalog module.
     *
     * @response 200 {"data": {"message": "You can manage programs and courses."}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function __invoke(): JsonResource
    {
        return new JsonResource(['message' => 'You can manage programs and courses.']);
    }
}
