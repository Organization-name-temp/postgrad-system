<?php

namespace Modules\Grading;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The Grading domain module (ADR-0020): it owns the Grade, the Grade Sheet, and — in later
 * Phase-4 tickets — the Incomplete lifecycle and the GPA/CGPA read model. That last placement
 * supersedes architecture.md §5, which assigned the GPA read model to Records: Records is a
 * document-issuance context and is not born until Phase 6, while a GPA is grade arithmetic and
 * belongs beside the Grades it is arithmetic over.
 *
 * Auto-discovered from bootstrap/providers.php by dropping the module directory in place
 * (ADR-0007); deptrac governs what it may import ({Identity, Catalog, Enrollment}\Public in
 * ticket 01 — never another module's Internal), and the dependency on Enrollment runs one way
 * only (ADR-0022), so neither module needs the other's test doubles.
 *
 * There is nothing to bind in the container yet: `Grading\Public` ships its route file in
 * ticket 01, and the read contract Phase 5's Standing and Phase 6's Transcript consume arrives
 * with the GPA read model. The cross-module facades this module reads are bound by their own
 * providers.
 */
class GradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are already
        // registered from the §3.2 matrix by Identity (ADR-0008); Grading consumes
        // grades.enter via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
