<?php

namespace Modules\AcademicStanding;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AcademicStanding\Internal\Support\DerivedCreditHourSummaries;
use Modules\AcademicStanding\Public\CreditHourSummaries;

final class AcademicStandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreditHourSummaries::class, DerivedCreditHourSummaries::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Public/Http/routes.php');
    }
}
