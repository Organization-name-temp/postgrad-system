<?php

namespace Modules\Admissions;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdmissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        $this->registerRateLimiters();

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // registered from the §3.2 matrix by Identity (ADR-0008); Admissions
        // consumes them via `can:` middleware, adding no permissions of its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
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
