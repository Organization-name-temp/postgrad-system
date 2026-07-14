<?php

namespace Modules\Identity;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Internal\Audit\EloquentAuditLogger;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Internal\Registration\EloquentApplicantRegistrar;
use Modules\Identity\Internal\Registration\EloquentEmailVerifier;
use Modules\Identity\Public\Contracts\ApplicantRegistrar;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\EmailVerifier;
use Modules\Identity\Public\Enums\Permission;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogger::class, EloquentAuditLogger::class);
        $this->app->bind(ApplicantRegistrar::class, EloquentApplicantRegistrar::class);
        $this->app->bind(EmailVerifier::class, EloquentEmailVerifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        $this->registerPermissionGates();

        // The module owns its /api/v1 routes (ADR-0002).
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }

    /**
     * Register one Gate per §3.2 permission, keyed by the permission's string
     * value, so `can:` middleware and Gate checks resolve against the user's
     * effective permissions (ADR-0008). The matrix is the single source of truth.
     */
    private function registerPermissionGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                static fn (User $user): bool => $user->hasPermission($permission),
            );
        }
    }
}
