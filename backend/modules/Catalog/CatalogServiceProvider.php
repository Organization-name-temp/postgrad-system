<?php

namespace Modules\Catalog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Internal\Support\EloquentCourseCatalog;
use Modules\Catalog\Internal\Support\EloquentCurriculumCatalog;
use Modules\Catalog\Internal\Support\EloquentProgramCatalog;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\ProgramCatalog;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The cross-module read facades (ADR-0012): consumers depend on the
        // Catalog\Public contracts; the Eloquent readers live in Catalog\Internal.
        $this->app->bind(ProgramCatalog::class, EloquentProgramCatalog::class);
        $this->app->bind(CourseCatalog::class, EloquentCourseCatalog::class);
        $this->app->bind(CurriculumCatalog::class, EloquentCurriculumCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // already registered from the §3.2 matrix by Identity (ADR-0008); Catalog
        // consumes them via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
