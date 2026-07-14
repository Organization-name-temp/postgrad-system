<?php

namespace Modules\Notifications;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Internal\Notifier\EloquentNotifier;
use Modules\Notifications\Public\Contracts\Notifier;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Other modules resolve the Public contract; it is backed by the Internal
        // Eloquent writer (mirrors Identity's AuditLogger binding).
        $this->app->bind(Notifier::class, EloquentNotifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002).
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
