<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Identity\Internal\Http\Resources\AuthUserResource;
use Modules\Identity\Internal\Models\User;

class UpdateCurrentUserController
{
    /**
     * Update current user.
     *
     * Persists the user's preferred UI language so the next session opens in it.
     *
     * @bodyParam preferred_locale string required The UI language (en or ar). Example: ar
     *
     * @response 200 {"data": {"id": 1, "name": "Student Affairs", "email": "r05@ejust.test", "preferred_locale": "ar", "roles": [{"code": "R05", "name": "Student Affairs Staff"}], "permissions": ["catalog.view"]}}
     * @response 422 {"message": "The selected preferred locale is invalid.", "errors": {"preferred_locale": ["The selected preferred locale is invalid."]}}
     */
    public function __invoke(Request $request): AuthUserResource
    {
        $validated = $request->validate([
            'preferred_locale' => ['required', 'string', 'in:en,ar'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['preferred_locale' => $validated['preferred_locale']]);

        return new AuthUserResource($user);
    }
}
