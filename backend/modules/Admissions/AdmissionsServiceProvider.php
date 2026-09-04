<?php

namespace Modules\Admissions;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Admissions\Internal\Console\Commands\FlagOverdueApplications;
use Modules\Admissions\Internal\Console\Commands\SendInterviewReminders;
use Modules\Admissions\Internal\Listeners\SendAcceptanceNotification;
use Modules\Admissions\Public\Events\AdmissionAccepted;

class AdmissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        $this->registerRateLimiters();
        $this->registerConsoleCommands();

        // The acceptance mint's one Phase-2 side-effect (ADR-0013): the applicant is
        // notified they were accepted. Wired explicitly (not by auto-discovery) so the
        // module's single domain event and its listener stay visible in one place.
        Event::listen(AdmissionAccepted::class, SendAcceptanceNotification::class);

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // registered from the §3.2 matrix by Identity (ADR-0008); Admissions
        // consumes them via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }

    /**
     * The module's manually-run console commands (ticket 11): the SLA overdue-flagger
     * and the interview-reminder nudge. Registered only when running in the console —
     * they have no HTTP surface — and left to Laravel's container for their `handle()`
     * dependencies (the Public facades they read). Both notify only; neither changes
     * any application status.
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FlagOverdueApplications::class,
                SendInterviewReminders::class,
            ]);
        }
    }

    /**
     * Rate limiters this module's routes attach by name.
     *
     * `register` throttles the unauthenticated self-registration door per client IP
     * — abuse resistance is out of scope for this build (ADR-0005), so this is a
     * light backstop against a script hammering the endpoint, not a hardened control.
     *
     * `verify-resend` caps how often an applicant can re-request their email
     * verification link (BR-72, ticket 04): a handful per rolling day, keyed per
     * authenticated user so one applicant cannot exhaust another's budget.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip() ?? 'unknown'));

        RateLimiter::for('verify-resend', static fn (Request $request): Limit => Limit::perDay(
            (int) config('admissions.verification_resend_max_per_day'),
        )->by('verify-resend:'.($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown')));
    }
}
