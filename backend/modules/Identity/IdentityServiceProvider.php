<?php

namespace Modules\Identity;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Internal\Audit\EloquentAuditLogger;
use Modules\Identity\Public\Contracts\AuditLogger;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogger::class, EloquentAuditLogger::class);
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
