<?php

namespace Modules\Notifications\Public\Contracts;

use Modules\Notifications\Public\Enums\NotificationType;

/**
 * The one way to raise an in-app notification for a User. Other modules depend on
 * this contract (Notifications\Public), never on the storage model — mirroring
 * Identity's `AuditLogger`. The recipient is named by opaque user id (ADR-0012);
 * Notifications never loads a User model.
 *
 * No rendered text is passed or stored: a notification is a {@see NotificationType}
 * plus a free-form `$payload`, and the SPA renders it in the reader's language.
 */
interface Notifier
{
    /**
     * Enqueue a notification for the given user.
     *
     * @param  int  $userId  The recipient (opaque id — no FK loaded here).
     * @param  array<string, mixed>  $payload  Data the SPA interpolates when rendering the type.
     */
    public function notify(int $userId, NotificationType $type, array $payload = []): void;

    /**
     * Enqueue at most once for the caller-owned stable idempotency key.
     *
     * @param  array<string, mixed>  $payload
     */
    public function notifyOnce(int $userId, NotificationType $type, string $key, array $payload = []): void;
}
