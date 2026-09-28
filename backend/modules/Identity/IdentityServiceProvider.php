<?php

namespace Modules\Identity;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Internal\Assignments\EloquentDeanAssignments;
use Modules\Identity\Internal\Assignments\EloquentDepartmentHeadAssignments;
use Modules\Identity\Internal\Audit\EloquentAuditLogger;
use Modules\Identity\Internal\Console\Commands\RecoverStudentIdentification;
use Modules\Identity\Internal\Directory\EloquentDirectory;
use Modules\Identity\Internal\Enrollment\EloquentStudentCurriculumBindings;
use Modules\Identity\Internal\Enrollment\EloquentStudentDirectory;
use Modules\Identity\Internal\Enrollment\EloquentStudentEnrollment;
use Modules\Identity\Internal\Enrollment\EloquentStudentGuard;
use Modules\Identity\Internal\Enrollment\EloquentStudentLifecycleWriter;
use Modules\Identity\Internal\Http\Middleware\EnsureAdministrator;
use Modules\Identity\Internal\Http\Middleware\EnsureRole;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Internal\Registration\EloquentApplicantRegistrar;
use Modules\Identity\Internal\Registration\EloquentEmailVerifier;
use Modules\Identity\Internal\Support\PostgresTranscriptReadGuard;
use Modules\Identity\Public\Contracts\ApplicantRegistrar;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\EmailVerifier;
use Modules\Identity\Public\Contracts\StudentCurriculumBindings;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Contracts\StudentLifecycleWriter;
use Modules\Identity\Public\Enums\Permission;
use Modules\Identity\Public\Http\Middleware\RequireSpaSession;
use Modules\Identity\Public\TranscriptReadGuard;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TranscriptReadGuard::class, PostgresTranscriptReadGuard::class);
        $this->app->bind(AuditLogger::class, EloquentAuditLogger::class);
        $this->app->bind(ApplicantRegistrar::class, EloquentApplicantRegistrar::class);
        $this->app->bind(EmailVerifier::class, EloquentEmailVerifier::class);
        $this->app->bind(DeanAssignments::class, EloquentDeanAssignments::class);
        $this->app->bind(DepartmentHeadAssignments::class, EloquentDepartmentHeadAssignments::class);
        $this->app->bind(Directory::class, EloquentDirectory::class);
        $this->app->bind(StudentEnrollment::class, EloquentStudentEnrollment::class);
        $this->app->bind(StudentDirectory::class, EloquentStudentDirectory::class);
        $this->app->bind(StudentGuard::class, EloquentStudentGuard::class);
        $this->app->bind(StudentLifecycleWriter::class, EloquentStudentLifecycleWriter::class);
        $this->app->bind(StudentCurriculumBindings::class, EloquentStudentCurriculumBindings::class);
    }

    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RecoverStudentIdentification::class]);
        }

        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        $this->registerPermissionGates();

        // Persona-specific surfaces are narrowed by role as well as capability without
        // extending the fixed permission matrix (ADR-0008).
        $router->aliasMiddleware('admin', EnsureAdministrator::class);
        $router->aliasMiddleware('role', EnsureRole::class);
        $router->aliasMiddleware('spa-session', RequireSpaSession::class);

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
