<?php

namespace Modules\AcademicStanding;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AcademicStanding\Internal\Console\Commands\ReconcileAcademicStanding;
use Modules\AcademicStanding\Internal\Listeners\ReconcileStandingAfterGrade;
use Modules\AcademicStanding\Internal\Support\DerivedCreditHourSummaries;
use Modules\AcademicStanding\Internal\Support\DerivedStandingRecords;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\Grading\Public\Events\GradeFinalized;

final class AcademicStandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreditHourSummaries::class, DerivedCreditHourSummaries::class);
        $this->app->bind(StandingRecords::class, DerivedStandingRecords::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');
        Event::listen(GradeFinalized::class, ReconcileStandingAfterGrade::class);
        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileAcademicStanding::class]);
        }
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Public/Http/routes.php');
    }
}
