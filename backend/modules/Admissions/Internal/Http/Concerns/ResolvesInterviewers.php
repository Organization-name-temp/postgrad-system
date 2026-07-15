<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Modules\Admissions\Internal\Http\Resources\InterviewSlotResource;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\UserSummary;

/**
 * Resolve the assigned interviewer (a faculty User) for a whole set of interview
 * slots in one Identity\Public directory read and stash the {@see UserSummary}
 * on each slot as `resolved_interviewer` (ADR-0012, mirroring {@see ResolvesQueuePrograms}).
 * The {@see InterviewSlotResource} renders the interviewer's display name from that
 * stashed summary, so listing a slot pool is a single directory read rather than one
 * lookup per row. Interviewers are referenced by opaque user id (no FK); a dropped
 * reference simply stashes null.
 */
trait ResolvesInterviewers
{
    /**
     * @param  Collection<int, InterviewSlot>  $slots
     */
    protected function attachInterviewers(Collection $slots, Directory $directory): void
    {
        $ids = $slots->pluck('interviewer_id')->filter()->unique()->values()->all();
        $summaries = $ids === [] ? [] : $directory->findMany($ids);

        $slots->each(function (InterviewSlot $slot) use ($summaries): void {
            $slot->setAttribute('resolved_interviewer', $summaries[$slot->interviewer_id] ?? null);
        });
    }
}
