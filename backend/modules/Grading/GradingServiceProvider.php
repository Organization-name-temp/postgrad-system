<?php

namespace Modules\Grading;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Grading\Internal\Console\Commands\ConvertExpiredIncompletes;
use Modules\Grading\Internal\Console\Commands\FlagLateSheets;
use Modules\Grading\Internal\Console\Commands\SendIncompleteReminders;
use Modules\Grading\Internal\Events\GradeFinalized;
use Modules\Grading\Internal\Listeners\SendGradeFinalizedNotifications;
use Modules\Grading\Internal\Listeners\SendIncompleteDeclaredNotifications;
use Modules\Grading\Internal\Support\EloquentGradingRecords;
use Modules\Grading\Public\GradingRecords;

/**
 * The Grading domain module (ADR-0020): it owns the Grade, the Grade Sheet, and — from ticket
 * 04 — the GPA/CGPA read model. That last placement supersedes architecture.md §5, which
 * assigned the GPA read model to Records: Records is a document-issuance context and is not
 * born until Phase 6, while a GPA is grade arithmetic and belongs beside the Grades it is
 * arithmetic over.
 *
 * Auto-discovered from bootstrap/providers.php by dropping the module directory in place
 * (ADR-0007); deptrac governs what it may import ({Identity, Catalog, Calendar, Enrollment,
 * Notifications}\Public — never another module's Internal), and the dependency on Enrollment
 * runs one way only (ADR-0022), so neither module needs the other's test doubles.
 *
 * `Grading\Public` shipped its route file in ticket 01; {@see GradingRecords} is the read
 * contract Phase 5's Standing and Phase 6's Transcript consume, bound here to its Eloquent
 * reader in `Grading\Internal`. The cross-module facades this module itself reads are bound by
 * their own providers.
 */
class GradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GradingRecords::class, EloquentGradingRecords::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');
        $this->registerConsoleCommands();

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are already
        // registered from the §3.2 matrix by Identity (ADR-0008); Grading consumes
        // grades.enter/grades.finalize via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');

        // GradeFinalized carries side-effects only: the Student notification (ticket 03), and —
        // registered alongside it, reacting to the same event — the Incomplete-declared
        // notification an Initial round's finalize also produces (ticket 05). Phase 5's
        // standing recompute hangs its own listener off this same event later without touching
        // the finalize (the Admissions AdmissionAccepted precedent).
        Event::listen(GradeFinalized::class, SendGradeFinalizedNotifications::class);
        Event::listen(GradeFinalized::class, SendIncompleteDeclaredNotifications::class);
    }

    /**
     * The module's manually-run console commands: the Incomplete reminder nudge and the
     * deadline-expiry converter — the one automated Course Status change in the system (ticket
     * 05, SRS §4.7.3.3) — and the late-Sheet flag (ticket 09), which like the reminder changes
     * nothing at all. Registered only when running in the console, the Admissions
     * FlagOverdueApplications/SendInterviewReminders precedent.
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendIncompleteReminders::class,
                ConvertExpiredIncompletes::class,
                FlagLateSheets::class,
            ]);
        }
    }
}
