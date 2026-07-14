<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Admissions\Internal\Models\Application;

/**
 * Resolves "the signed-in applicant's Application" for the applicant-owned wizard
 * endpoints (get/save/submit my application, resend my verification link). The
 * applicant owns a single Application per Cycle; re-applying in a later Cycle
 * produces a new one, so we take the most recent. A reviewer (or anyone without an
 * Application) gets a 404 — these routes are the applicant's own surface.
 */
trait ResolvesCurrentApplication
{
    protected function currentApplication(Request $request): Application
    {
        return Application::query()
            ->with('documents')
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->latest('id')
            ->firstOrFail();
    }
}
