<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Http\Controllers\FinalDecisionController;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;

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
    use IdentifiesAdministrator;

    /**
     * The reviewer queue the signed-in user should see for the given statuses: an
     * administrator oversees every Faculty unscoped (ADR-0015), while a Dean sees only
     * the applications whose Faculty they are assigned to. This is the single place the
     * admin-vs-scoped choice is made for both Dean queues (review, decisions), so the
     * two surfaces pick their queue identically.
     *
     * @param  list<ApplicationStatus>  $statuses
     * @return Collection<int, Application>
     */
    protected function deanOrAdminQueue(
        Request $request,
        array $statuses,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
        Directory $directory,
    ): Collection {
        if ($this->actingAsAdministrator($request, $directory)) {
            return $this->statusQueue($statuses, $catalog);
        }

        return $this->deanFacultyQueue(
            $assignments->facultiesForDean((int) $request->user()?->getAuthIdentifier()),
            $statuses,
            $catalog,
        );
    }

    /**
     * The applications **awaiting a Dean's decision**: those resting at `interview_completed`
     * (a first decision is due) and those held at `conditionally_accepted` / `waitlisted` (a
     * resolution is due), faculty-scoped exactly as every other Dean queue is.
     *
     * One definition, two readers (phase 4.5, ticket 08): the decisions queue itself
     * ({@see FinalDecisionController::index()}) and the Dashboard Card that counts it. Naming
     * the statuses in each place separately is how a Card comes to disagree with the screen it
     * links into — a Dean told "3 waiting" who opens the queue and finds 5 has been given worse
     * than nothing.
     *
     * @return Collection<int, Application>
     */
    protected function awaitingDecisionQueue(
        Request $request,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
        Directory $directory,
    ): Collection {
        return $this->deanOrAdminQueue($request, [
            ApplicationStatus::InterviewCompleted,
            ApplicationStatus::ConditionallyAccepted,
            ApplicationStatus::Waitlisted,
        ], $assignments, $catalog, $directory);
    }

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
     * Every application in the given statuses, oldest-first (by when they finalized),
     * each with its primary Program resolved and stashed in one facade read (ADR-0012)
     * — the unscoped queue. This is what an administrator sees (ADR-0015): oversight
     * across all Faculties and Cycles. The faculty-scoped Dean queue is this, filtered.
     *
     * @param  list<ApplicationStatus>  $statuses
     * @return Collection<int, Application>
     */
    protected function statusQueue(array $statuses, ProgramCatalog $catalog): Collection
    {
        $applications = Application::query()
            ->with('statusTransitions')
            ->whereIn('status', $statuses)
            ->orderBy('finalized_at')
            ->orderBy('id')
            ->get();

        $this->attachPrimaryPrograms($applications, $catalog);

        return $applications;
    }

    /**
     * A faculty-scoped Dean queue: the applications in the given statuses whose primary
     * Program's Faculty is among the Dean's assigned Faculties, oldest-first (by when
     * they finalized), each with its primary Program resolved and stashed. No assigned
     * Faculties ⇒ an empty queue. Shared by the Dean review queue (`under_dean_review`,
     * ticket 08) and the Dean decisions queue (post-interview, ticket 12), which differ
     * only in the statuses they list — the faculty scope + resolve is identical. An
     * administrator uses {@see self::statusQueue()} instead, which is unscoped (ADR-0015).
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

        // Keep only the applications whose Faculty this Dean covers.
        return $this->statusQueue($statuses, $catalog)
            ->filter(function (Application $application) use ($facultyIds): bool {
                $summary = $application->getAttribute('resolved_primary_program');

                return $summary !== null && in_array($summary->facultyId, $facultyIds, true);
            })
            ->values();
    }
}
