<?php

namespace Modules\AcademicStanding\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\AcademicStanding\Internal\Support\StandingReconciler;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Identity\Public\Contracts\StudentDirectory;

final class ReconcileAcademicStanding extends Command
{
    protected $signature = 'academic-standing:reconcile {student? : Student anchor id}';

    protected $description = 'Reconcile derived Academic Standing observations and notifications';

    public function handle(StudentDirectory $students, TerminationCases $terminationCases, StandingReconciler $reconciler): int
    {
        $argument = $this->argument('student');
        $ids = $argument === null ? $students->allStudentIds() : [(int) $argument];
        foreach ($ids as $studentId) {
            $terminationCases->reconcile($studentId);
            $reconciler->reconcile($studentId, 'command');
        }
        $this->info('Academic Standing reconciled for '.count($ids).' Student(s).');

        return self::SUCCESS;
    }
}
