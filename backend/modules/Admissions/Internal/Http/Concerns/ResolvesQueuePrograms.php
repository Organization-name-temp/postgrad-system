<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Http\Controllers\FinalDecisionController;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;

/**
 * Resolve the primary Program summary for a whole reviewer queue in one facade read
 * and stash it on each row as `resolved_primary_program` (ADR-0012). Shared by the
 * Student Affairs queue ({@see ApplicationScreeningController})
 * and the Dean queues ({@see DeanReviewController} review,
 * {@see FinalDecisionController} decisions), which all render {@see ApplicationQueueResource}
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

    /**
     * A faculty-scoped Dean queue: the applications in the given statuses whose primary
     * Program's Faculty is among the Dean's assigned Faculties, oldest-first (by when
     * they finalized), each with its primary Program resolved and stashed. No assigned
     * Faculties ⇒ an empty queue. Shared by the Dean review queue (`under_dean_review`,
     * ticket 08) and the Dean decisions queue (post-interview, ticket 12), which differ
     * only in the statuses they list — the faculty scope + resolve is identical.
     *
     * @param  list<int>  $facultyIds
     * @param  list<ApplicationStatus>  $statuses
     * @return Collection<int, Application>
     */
    protected function deanFacultyQueue(array $facultyIds, array $statuses, ProgramCatalog $catalog): Collection
    {
        if ($facultyIds === []) {
            return new Collection;
        }

        $applications = Application::query()
            ->with('statusTransitions')
            ->whereIn('status', $statuses)
            ->orderBy('finalized_at')
            ->orderBy('id')
            ->get();

        // One facade read for the whole queue (ADR-0012); keep only the applications
        // whose Faculty this Dean covers.
        $this->attachPrimaryPrograms($applications, $catalog);

        return $applications
            ->filter(function (Application $application) use ($facultyIds): bool {
                $summary = $application->getAttribute('resolved_primary_program');

                return $summary !== null && in_array($summary->facultyId, $facultyIds, true);
            })
            ->values();
    }
}
