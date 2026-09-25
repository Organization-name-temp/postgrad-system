<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Public\TerminationCases;

final readonly class AcademicStandingReconciler
{
    public function __construct(private TerminationCases $terminationCases, private StandingReconciler $standing) {}

    public function reconcile(int $studentId, ?string $causeType = null, ?int $causeId = null): void
    {
        DB::transaction(function () use ($studentId, $causeType, $causeId): void {
            $this->terminationCases->reconcile($studentId);
            $this->standing->reconcile($studentId, $causeType, $causeId);
        });
    }

    public function reconcileStanding(int $studentId, ?string $causeType = null, ?int $causeId = null): void
    {
        $this->standing->reconcile($studentId, $causeType, $causeId);
    }

    public function reconcileRegistrationPolicy(int $studentId): void
    {
        $this->standing->reconcileRegistrationPolicy($studentId);
    }
}
