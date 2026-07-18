<?php

namespace Modules\Notifications\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Internal\Models\Notification;

/**
 * A notification in the { data: ... } envelope. It carries the type and its raw
 * payload — never rendered text — so the SPA localises it from the type via the
 * `notifications` i18n namespace (ticket 02). `read_at` is null while unread.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->getKey(),
            'type' => $notification->type->value,
            // Always an object so the SPA can interpolate keys uniformly ({} when empty).
            'data' => (object) ($notification->data ?? []),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
