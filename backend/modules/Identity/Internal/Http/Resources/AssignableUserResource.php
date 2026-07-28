<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Internal\Models\User;

/**
 * A user as an admin assignment screen's picker needs them — just id, name, and
 * email. A thin projection of the User for the "assign a Dean" (ticket 08) and
 * "assign a Department Head" (phase 4, ticket 02) dropdowns, in the same
 * `{ data: ... }` envelope every other list uses. Which *role* is being picked is
 * the calling controller's filter, not this resource's concern, so both share it.
 *
 * @mixin User
 */
class AssignableUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
