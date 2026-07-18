<?php

namespace Modules\Notifications\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Notifications\Internal\Http\Resources\NotificationResource;
use Modules\Notifications\Internal\Models\Notification;

/**
 * @group Notifications
 *
 * A signed-in user's own in-app notifications — the data behind the shell's bell
 * (ticket 02). Every endpoint is scoped to the authenticated user by
 * {@see Notification::scopeForUser()}, so no request can list, count, or mutate
 * another user's notifications (a foreign id simply is not found → 404). Content
 * is a type + payload; the SPA renders it bilingually, so nothing here is
 * localised server-side.
 */
class NotificationController
{
    /**
     * List my notifications.
     *
     * Paginated, newest-first. Scoped to the authenticated user.
     *
     * @response 200 {"data": [{"id": 3, "type": "interview.scheduled", "data": {"scheduled_at": "2026-09-01T10:00:00+00:00"}, "read_at": null, "created_at": "2026-07-14T12:00:00+00:00"}], "links": {"first": "/?page=1", "last": "/?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "from": 1, "last_page": 1, "path": "/", "per_page": 15, "to": 1, "total": 1}}
     */
    public function index(): AnonymousResourceCollection
    {
        $notifications = Notification::query()
            ->forUser((int) Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return NotificationResource::collection($notifications);
    }

    /**
     * My unread count.
     *
     * The number the bell badge shows. Scoped to the authenticated user.
     *
     * @response 200 {"data": {"count": 3}}
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::query()->forUser((int) Auth::id())->unread()->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    /**
     * Mark one notification read.
     *
     * Idempotent. A notification that is not the caller's is not found (404), so
     * it can never be read on someone else's behalf.
     *
     * @urlParam notification integer required The ID of the notification. Example: 3
     *
     * @response 204
     * @response 404 {"message": "No query results for model."}
     */
    public function markRead(int $notification): Response
    {
        Notification::query()
            ->forUser((int) Auth::id())
            ->findOrFail($notification)
            ->markRead();

        return response()->noContent();
    }

    /**
     * Mark all my notifications read.
     *
     * Clears the caller's unread count in one call; touches only their own rows.
     *
     * @response 204
     */
    public function markAllRead(): Response
    {
        Notification::query()
            ->forUser((int) Auth::id())
            ->unread()
            ->update(['read_at' => Carbon::now()]);

        return response()->noContent();
    }
}
