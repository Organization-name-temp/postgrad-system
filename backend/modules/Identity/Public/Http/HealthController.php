<?php

namespace Modules\Identity\Public\Http;

use Illuminate\Http\JsonResponse;

class HealthController
{
    /**
     * Service health check.
     *
     * A public, unauthenticated liveness probe used by the SPA to confirm the
     * API is reachable.
     *
     * @response 200 {"data": {"status": "ok", "app": "PRS"}}
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'app' => config('app.name'),
            ],
        ]);
    }
}
