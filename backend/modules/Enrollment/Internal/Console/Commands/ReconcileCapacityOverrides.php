<?php

namespace Modules\Enrollment\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Enrollment\Internal\Support\CapacityOverrideReconciler;

final class ReconcileCapacityOverrides extends Command
{
    protected $signature = 'enrollment:reconcile-capacity {offering?}';

    protected $description = 'Reconcile waiting capacity-override requests against current seats';

    public function handle(CapacityOverrideReconciler $reconciler): int
    {
        $offeringId = $this->argument('offering');
        if ($offeringId === null) {
            $reconciler->reconcileAll();
        } else {
            $reconciler->reconcileOffering((int) $offeringId);
        }

        return self::SUCCESS;
    }
}
