<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Internal\Models\User;

/**
 * A Dean (R07 user) as the admin assignment screen's picker needs them — just id,
 * name, and email. A thin projection of the User for the "assign a Dean" dropdown
 * (ticket 08), in the same `{ data: ... }` envelope every other list uses.
 *
 * @mixin User
 */
class DeanResource extends JsonResource
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
