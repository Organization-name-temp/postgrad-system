<?php

namespace Modules\Enrollment;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The Enrollment domain module (ADR-0017): it owns Course Offerings — and, in later
 * Phase-3 tickets, Registration Requests, Enrollments, Class Sessions and Attendance.
 * Auto-discovered from bootstrap/providers.php by dropping the module directory in
 * place (ADR-0007); deptrac governs what it may import ({Identity, Catalog, Calendar,
 * Notifications}\Public — never another module's Internal).
 *
 * `Enrollment\Public` ships empty beyond its route file in Phase 3 (ADR-0017): the
 * offering/roster read surface other modules will consume is built in Phase 4 against
 * grading, its first real consumer. There is nothing to bind in the container yet —
 * the cross-module facades this module reads are bound by their own providers.
 */
class EnrollmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // already registered from the §3.2 matrix by Identity (ADR-0008); Enrollment
        // consumes calendar.manage via `can:` middleware, adding no permissions of
        // its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
