<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Identity\Internal\Http\Resources\AuthUserResource;
use Modules\Identity\Internal\Models\User;

class MeController
{
    /**
     * Current user.
     *
     * The authenticated user + roles (and, from ticket 05, effective
     * permissions); the SPA's authority source for route guards.
     *
     * @response 200 {"data": {"id": 1, "name": "Student Affairs", "email": "r05@ejust.test", "preferred_locale": "en", "roles": [{"code": "R05", "name": "Student Affairs Staff"}], "permissions": ["documents.upload", "application.view_status", "application.screen", "interview.schedule", "courses.register_for_student", "registration.approve", "catalog.view", "grades.enter", "grades.view_student", "transcript.view_unofficial", "transcript.issue_official", "transcript.export", "standing.monitor", "calendar.manage"]}}
     * @response 401 {"message": "Unauthenticated."}
     */
    public function __invoke(Request $request): AuthUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AuthUserResource($user);
    }
}
