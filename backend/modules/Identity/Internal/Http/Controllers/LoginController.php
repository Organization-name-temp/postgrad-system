<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Internal\Http\Resources\AuthUserResource;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Internal\Support\LoginAttempts;
use Modules\Identity\Public\Contracts\AuditLogger;

class LoginController
{
    /**
     * Log in.
     *
     * Authenticates via Sanctum's SPA cookie session (Q10). Requires a first-party
     * session and limits failed attempts by normalized email and client IP.
     *
     * @unauthenticated
     *
     * @response 200 {"data": {"id": 1, "name": "Student Affairs", "email": "r05@ejust.test", "preferred_locale": "en", "roles": [{"code": "R05", "name": "Student Affairs Staff"}], "permissions": ["documents.upload", "application.view_status", "application.screen", "interview.schedule", "courses.register_for_student", "registration.approve", "catalog.view", "grades.enter", "grades.view_student", "transcript.view_unofficial", "transcript.issue_official", "transcript.export", "standing.monitor", "calendar.manage"]}}
     * @response 422 {"message": "These credentials do not match our records.", "errors": {"email": ["These credentials do not match our records."]}}
     * @response 400 {"message": "A first-party SPA session is required."}
     * @response 429 {"message": "Too many login attempts. Please try again in 60 seconds."}
     */
    public function __invoke(Request $request, AuditLogger $audit, LoginAttempts $attempts): AuthUserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return $attempts->serialized($credentials['email'], $request->ip(), function () use ($request, $credentials, $audit, $attempts): AuthUserResource {
            if (! Auth::attempt($credentials)) {
                $attempts->failed($credentials['email'], $request->ip());
                $audit->record('login_failed', null, [
                    'email' => $credentials['email'],
                    'ip' => $request->ip(),
                ]);

                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            $request->session()->regenerate();
            $attempts->succeeded($credentials['email']);

            /** @var User $user */
            $user = Auth::user();

            $audit->record('login', (int) $user->getKey(), ['ip' => $request->ip()]);

            return new AuthUserResource($user);
        });
    }
}
