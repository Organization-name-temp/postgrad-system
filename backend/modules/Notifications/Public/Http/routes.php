<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Internal\Http\Controllers\NotificationController;

/*
| Notifications routes, mounted under /api/v1 by NotificationsServiceProvider.
|
| Every endpoint is authenticated and implicitly scoped to the signed-in user —
| there is no permission gate, because a user's own notifications are theirs by
| definition (the scoping, not RBAC, is the boundary). The literal /unread-count
| and /read-all segments are declared before the {notification} route, and the id
| is constrained to an integer, so neither literal is ever read as an id.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereNumber('notification');
});
