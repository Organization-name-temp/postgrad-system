<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;

/**
 * Resolve the primary Program summary for a whole reviewer queue in one facade read
 * and stash it on each row as `resolved_primary_program` (ADR-0012). Shared by the
 * Student Affairs queue ({@see ApplicationScreeningController})
 * and the Dean queue ({@see DeanReviewController}),
 * which both render {@see ApplicationQueueResource}
 * from that stashed summary — so the resolve-and-stash lives in one place rather than
 * being repeated per queue. A dropped reference (no FK) simply stashes null.
 */
trait ResolvesQueuePrograms
{
    /**
     * @param  Collection<int, Application>  $applications
     */
    protected function attachPrimaryPrograms(Collection $applications, ProgramCatalog $catalog): void
    {
        $ids = $applications->pluck('primary_program_id')->filter()->unique()->values()->all();
        $summaries = $ids === [] ? [] : $catalog->findMany($ids);

        $applications->each(function (Application $application) use ($summaries): void {
            $id = $application->primary_program_id;
            $application->setAttribute('resolved_primary_program', $id === null ? null : ($summaries[$id] ?? null));
        });
    }
}
