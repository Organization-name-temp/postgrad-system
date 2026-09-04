<?php

namespace Modules\Enrollment\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Enrollment\Internal\Support\AutomaticExemptionApplicator;

class ReconcileAutomaticExemptions extends Command
{
    protected $signature = 'enrollment:reconcile-automatic-exemptions
                            {student : Identity Student anchor id}';

    protected $description = 'Reapply captured admission evidence for one Student idempotently';

    public function handle(AutomaticExemptionApplicator $applicator): int
    {
        $studentId = filter_var($this->argument('student'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($studentId === false) {
            $this->error('The student argument must be a positive integer.');

            return self::INVALID;
        }

        $created = $applicator->applyForStudent($studentId);
        $decisionLabel = $created === 1 ? 'decision' : 'decisions';
        $this->info("Created {$created} automatic Exemption {$decisionLabel} for Student {$studentId}.");

        return self::SUCCESS;
    }
}
