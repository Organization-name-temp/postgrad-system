<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Internal\Http\Resources\AuthUserResource;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\AuditLogger;

class LoginController
{
    /**
     * Log in.
     *
     * Authenticates via Sanctum's SPA cookie session (Q10).
     *
     * @unauthenticated
     *
     * @response 200 {"data": {"id": 1, "name": "Student Affairs", "email": "r05@ejust.test", "preferred_locale": "en", "roles": [{"code": "R05", "name": "Student Affairs Staff"}]}}
     * @response 422 {"message": "These credentials do not match our records.", "errors": {"email": ["These credentials do not match our records."]}}
     */
    public function __invoke(Request $request, AuditLogger $audit): AuthUserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            $audit->record('login_failed', null, [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $audit->record('login', (int) $user->getKey(), ['ip' => $request->ip()]);

        return new AuthUserResource($user);
    }
}
