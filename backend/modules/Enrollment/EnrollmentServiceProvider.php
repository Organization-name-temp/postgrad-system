<?php

namespace Modules\Enrollment;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Admissions\Public\Events\AdmissionAccepted;
use Modules\Calendar\Public\Events\SemesterChanged;
use Modules\Catalog\Public\Events\CourseChanged;
use Modules\Enrollment\Internal\Console\Commands\ReconcileAutomaticExemptions;
use Modules\Enrollment\Internal\Console\Commands\ReconcileCapacityOverrides;
use Modules\Enrollment\Internal\Console\Commands\ReconcileRemediation;
use Modules\Enrollment\Internal\Listeners\ApplyAutomaticPreparatoryExemptions;
use Modules\Enrollment\Internal\Listeners\ReconcileMustRetakeObligations;
use Modules\Enrollment\Internal\Listeners\ReconcileRemediationAfterCourseChange;
use Modules\Enrollment\Internal\Listeners\ReconcileRemediationAfterSemesterChange;
use Modules\Enrollment\Internal\Listeners\RejectCreditRequestsAfterFailedGrade;
use Modules\Enrollment\Internal\Support\EloquentAcademicRequestCloseout;
use Modules\Enrollment\Internal\Support\EloquentEnrollmentRecords;
use Modules\Enrollment\Internal\Support\EloquentExemptionDecisions;
use Modules\Enrollment\Internal\Support\EloquentMustRetakeObligations;
use Modules\Enrollment\Internal\Support\EloquentRegistrationPolicyWriter;
use Modules\Enrollment\Internal\Support\EloquentTransferDecisions;
use Modules\Enrollment\Internal\Support\EloquentWaiverDecisions;
use Modules\Enrollment\Internal\Support\RemediationReconciler;
use Modules\Enrollment\Public\AcademicRequestCloseout;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\MustRetakeObligations;
use Modules\Enrollment\Public\RegistrationPolicyWriter;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\WaiverDecisions;

/**
 * The Enrollment domain module (ADR-0017): it owns Course Offerings, Registration Requests,
 * Enrollments, Class Sessions and Attendance. Auto-discovered from bootstrap/providers.php
 * by dropping the module directory in place (ADR-0007); deptrac governs what it may import
 * ({Identity, Catalog, Calendar, Notifications}\Public — never another module's Internal).
 *
 * `Enrollment\Public` shipped empty beyond its route file in Phase 3; Phase 4 makes it
 * load-bearing (ADR-0017 deferred the read surface to "built against grading", ADR-0020 is
 * the consumer that arrived). {@see EnrollmentRecords} is the cross-module read facade
 * Grading resolves rosters and Offering routing facts through; the Eloquent reader lives in
 * `Enrollment\Internal`. The cross-module facades this module itself reads are bound by
 * their own providers.
 */
class EnrollmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The cross-module read facade (ADR-0012/0017): consumers depend on the
        // Enrollment\Public contract; the Eloquent reader lives in Enrollment\Internal.
        $this->app->bind(EnrollmentRecords::class, EloquentEnrollmentRecords::class);
        $this->app->bind(AcademicRequestCloseout::class, EloquentAcademicRequestCloseout::class);
        $this->app->bind(ExemptionDecisions::class, EloquentExemptionDecisions::class);
        $this->app->bind(TransferDecisions::class, EloquentTransferDecisions::class);
        $this->app->bind(WaiverDecisions::class, EloquentWaiverDecisions::class);
        $this->app->bind(MustRetakeObligations::class, EloquentMustRetakeObligations::class);
        $this->app->bind(RemediationObligations::class, RemediationReconciler::class);
        $this->app->bind(RegistrationPolicyWriter::class, EloquentRegistrationPolicyWriter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');

        Event::listen(AdmissionAccepted::class, ApplyAutomaticPreparatoryExemptions::class);
        Event::listen(GradeOutcomesFinalized::class, RejectCreditRequestsAfterFailedGrade::class);
        Event::listen(GradeOutcomesFinalized::class, ReconcileMustRetakeObligations::class);
        Event::listen(CourseChanged::class, ReconcileRemediationAfterCourseChange::class);
        Event::listen(SemesterChanged::class, ReconcileRemediationAfterSemesterChange::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileAutomaticExemptions::class, ReconcileCapacityOverrides::class, ReconcileRemediation::class]);
        }

        // The module owns its /api/v1 routes (ADR-0002). Authorization gates are
        // already registered from the §3.2 matrix by Identity (ADR-0008); Enrollment
        // consumes calendar.manage via `can:` middleware, adding no permissions of
        // its own.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Public/Http/routes.php');
    }
}
