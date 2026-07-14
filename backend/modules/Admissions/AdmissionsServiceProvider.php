<?php

namespace Modules\Admissions;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdmissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // registered from the §3.2 matrix by Identity (ADR-0008); Admissions
        // consumes them via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
