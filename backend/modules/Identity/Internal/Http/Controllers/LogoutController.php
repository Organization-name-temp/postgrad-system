<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Public\Contracts\AuditLogger;

class LogoutController
{
    /**
     * Log out.
     *
     * Ends the session.
     *
     * @response 204
     */
    public function __invoke(Request $request, AuditLogger $audit): Response
    {
        $actorId = $request->user()?->getAuthIdentifier();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $audit->record('logout', $actorId !== null ? (int) $actorId : null);

        return response()->noContent();
    }
}
