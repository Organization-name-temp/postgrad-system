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
     * permissions); the SPA's authority source for route guards. Once the user holds
     * the Student role (R02), `student` carries their `Student` anchor — University ID,
     * program, enrollment term, advisor — so a newly-admitted user sees their student
     * identity on sign-in (ADR-0013), including the opaque admission-time Curriculum
     * Version binding (ADR-0034); it is `null` for everyone who is not a Student.
     *
     * @response 200 {"data": {"id": 2, "name": "Sara Applicant", "email": "sara@example.com", "preferred_locale": "en", "roles": [{"code": "R02", "name": "Student"}], "permissions": ["courses.register_self", "exemption.submit", "transfer_waiver.submit", "grades.view_own", "transcript.view_unofficial", "transcript.export", "standing.monitor", "catalog.view"], "student": {"university_id": "2026-MSC-CE-0001", "program_id": 1, "curriculum_version_id": 12, "curriculum_version_binding_source": "admission_time", "enrollment_term": "Fall 2026", "admission_cycle_id": 1, "advisor_id": 3, "admitted_at": "2026-07-15T10:00:00+00:00"}}}
     * @response 401 {"message": "Unauthenticated."}
     */
    public function __invoke(Request $request): AuthUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AuthUserResource($user);
    }
}
