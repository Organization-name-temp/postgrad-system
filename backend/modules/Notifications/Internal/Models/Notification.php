<?php

namespace Modules\Notifications\Internal\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * A single in-app notification for one User (ticket 02). Created through the
 * {@see Notifier} contract; content is a
 * {@see NotificationType} plus a `data` payload the SPA renders bilingually — no
 * text is stored here. Only created_at is managed (no updated_at, like the audit
 * log); the one mutation is flipping read_at when the recipient reads it.
 *
 * The recipient is held as a plain `user_id` — the module references users by
 * opaque id and never loads Identity's User model (ADR-0012, the seam).
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property array<string, mixed>|null $data
 * @property string|null $deduplication_key
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /** created_at only — a notification is never touched after it is read. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'deduplication_key',
        'read_at',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }

    /**
     * Constrain a query to notifications belonging to the given user — the single
     * scoping rule behind every endpoint, so no request can reach another user's
     * notifications.
     *
     * @param  Builder<Notification>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Constrain a query to unread notifications (read_at is null).
     *
     * @param  Builder<Notification>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** Mark this notification read, once. A no-op if it is already read. */
    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = Carbon::now();
            $this->save();
        }
    }
}
