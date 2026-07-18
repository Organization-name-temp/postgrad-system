<?php

namespace Modules\Notifications\Internal\Notifier;

use Illuminate\Support\Carbon;
use Modules\Notifications\Internal\Models\Notification;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * The Eloquent-backed {@see Notifier}: it writes one unread notification row.
 * Mirrors Identity's EloquentAuditLogger — the Internal implementation the
 * ServiceProvider binds the Public contract to, so callers depend only on the
 * interface.
 */
class EloquentNotifier implements Notifier
{
    public function notify(int $userId, NotificationType $type, array $payload = []): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'data' => $payload !== [] ? $payload : null,
            'read_at' => null,
            'created_at' => Carbon::now(),
        ]);
    }
}
