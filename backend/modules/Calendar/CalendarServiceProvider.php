<?php

namespace Modules\Calendar;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Calendar\Internal\Support\EloquentAcademicCalendar;
use Modules\Calendar\Internal\Support\PostgresTranscriptReadGuard;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\TranscriptReadGuard;

/**
 * The supporting Calendar module (ADR-0018): the single authority for the Semester
 * and its period windows, consumed by everyone (offerings, registration, standing,
 * transcripts) and depending on no other domain module. Auto-discovered from
 * bootstrap/providers.php by dropping the module directory in place (ADR-0007).
 */
class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TranscriptReadGuard::class, PostgresTranscriptReadGuard::class);
        // The cross-module read facade (ADR-0012): consumers depend on the
        // Calendar\Public contract; the Eloquent reader lives in Calendar\Internal.
        $this->app->bind(AcademicCalendar::class, EloquentAcademicCalendar::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // already registered from the §3.2 matrix by Identity (ADR-0008); Calendar
        // consumes calendar.manage via `can:` middleware, adding no permissions of
        // its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
