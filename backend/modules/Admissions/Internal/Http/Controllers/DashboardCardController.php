<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Support\ReviewSla;

/**
 * @group Admissions
 *
 * Admissions' Dashboard Cards (phase 4.5, ADR-0024) — one for each side of the admissions counter:
 * the Applicant's own Application, and the Student Affairs screening queue. Each is **read-only**,
 * lives in the module that owns the data, is **independently authorized**, and returns typed domain
 * values (enum values, ids, counts, ISO dates) rather than pre-rendered display strings: the SPA
 * renders the status chip and the aging copy bilingually from the payload, exactly as Notifications
 * does.
 *
 * A Card introduces no domain logic of its own. The screening Card's aging and overdue threshold
 * are read from {@see ReviewSla} — the same business-day arithmetic and configured SLA the
 * overdue-flagging command uses — so a Card can never disagree with a notification a reviewer
 * already received, and overdue stays what it has always been: information only. Nothing here is
 * blocked, changed or auto-actioned by it.
 */
class DashboardCardController
{
    /** The three-row Card convention the shared `<DashboardCard>` is built around. */
    private const ROW_LIMIT = 3;

    /**
     * My Application Card.
     *
     * Where the acting Applicant's Application stands: its current status and when it entered that
     * status. The SPA renders the status as a chip and picks the "what happens next" copy from the
     * status itself, which is why the status travels as its enum value and never as a sentence —
     * the next action is a rendering of a state the domain already has, not a new fact about it.
     *
     * The Applicant owns one Application per Cycle, so the most recent is theirs — the same rule
     * the applicant wizard's `ResolvesCurrentApplication` applies, but resolved here to **null**
     * rather than to its 404: a user with no Application at all, possible between account creation
     * and the first draft and true of every non-applicant, gets `{"data": null}` and the Card's
     * empty state, because an Applicant who has not started is a legitimate state and not an error.
     * That `null` deliberately gets no `@response` scenario of its own: a scalar-null `data` beside
     * an object one makes Scribe emit a *union* response type, and the generated client then cannot
     * read the payload at all.
     *
     * Gated on `application.view_status` (§3.2 — Applicant / Student Affairs / Dean) and inherently
     * scoped on top of that: it reads the acting user's own Application and takes no id, so another
     * applicant's is unreachable from it.
     *
     * The SPA nonetheless gates the Card on the **Applicant Role**, not on that Permission: an
     * administrator inherits it (ADR-0008) and holds no Application, so a Permission-gated Card
     * would sit permanently empty on their Dashboard. The Permission decides who may *ask*; holding
     * an Application decides who has anything to see.
     *
     * @response 200 {"data": {"id": 1, "status": "under_sa_review", "status_entered_at": "2026-07-10T10:00:00+00:00", "finalized_at": "2026-07-10T10:00:00+00:00"}}
     */
    public function myApplication(Request $request): JsonResponse
    {
        $application = Application::query()
            ->with('statusTransitions')
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->latest('id')
            ->first();

        if ($application === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => (int) $application->getKey(),
            'status' => $application->status->value,
            'status_entered_at' => $application->enteredStatusAt($application->status)?->toIso8601String(),
            'finalized_at' => $application->finalized_at?->toIso8601String(),
        ]]);
    }

    /**
     * Screening Queue Card.
     *
     * How much screening work is waiting: how many Applications are pending, **how many of those
     * are overdue**, and the oldest few with their age — closing SRS FR-ADM-005.5, which has been
     * unbuilt since Phase 2 because there was no dashboard to display it on.
     *
     * Aging is the business-day count since the Application entered review (the Egyptian
     * Friday/Saturday weekend does not count) and the threshold is the configured screening SLA —
     * both read from {@see ReviewSla}, the same seam the `admissions:flag-overdue` command uses, so
     * this Card and that notification always agree. Neither is stored: the age is recomputed on
     * every read from the timeline.
     *
     * **Overdue is a count and a visual distinction, nothing more.** It blocks nothing, changes no
     * status and appends no timeline row, exactly as the existing flag-and-notify command does not.
     *
     * `sla_days` travels with the counts so the SPA can say *why* something is overdue without
     * hard-coding the university's threshold. An empty queue returns zeros and an empty list, and
     * the SPA renders the shared empty state rather than a bare `0`.
     *
     * Gated on `application.screen` (§3.2 — Student Affairs / Dean).
     *
     * The empty queue is described above rather than given its own `@response` scenario, for the same
     * reason the Card above has only one: an empty `applications` list beside a populated one makes
     * Scribe emit a union response type the generated client cannot read.
     *
     * @response 200 {"data": {"pending_count": 6, "overdue_count": 2, "sla_days": 5, "applications": [{"id": 1, "status": "under_sa_review", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "age_days": 7, "is_overdue": true, "review_entered_at": "2026-07-10T10:00:00+00:00"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function screeningQueue(): JsonResponse
    {
        $sla = ReviewSla::screening();

        // Aging cannot be expressed in SQL — a business-day count skips the weekend — so both the
        // overdue tally and the row order are derived over the waiting Applications with their
        // timelines loaded, exactly as the overdue-flagging command derives them. The set has to be
        // materialised for that, so `pending_count` is taken from it rather than asked for as a
        // second query: the no-materialising rule exists to stop a *needless* load, and a
        // reviewer's worklist is bounded by the work waiting on them.
        $waiting = Application::query()
            ->with('statusTransitions')
            ->where('status', $sla->status)
            ->orderBy('id')
            ->get();

        $now = now();

        /** @var list<array{application: Application, age_days: ?int}> $aged */
        $aged = $waiting->map(fn (Application $application): array => [
            'application' => $application,
            'age_days' => $sla->ageInDaysOf($application, $now),
        ])->values()->all();

        $overdue = array_filter($aged, fn (array $row): bool => $sla->isOverdue($row['age_days']));

        return response()->json(['data' => [
            'pending_count' => $waiting->count(),
            'overdue_count' => count($overdue),
            'sla_days' => $sla->slaDays,
            'applications' => $this->queueRows(array_slice($this->oldestFirst($aged), 0, self::ROW_LIMIT), $sla),
        ]]);
    }

    /**
     * The waiting Applications ordered by the **age the Card shows** — longest wait first — so
     * "the three oldest" means the same thing as the number printed beside each row.
     *
     * Ordering by `finalized_at` instead would disagree with that number: an Application returned
     * for revision and resubmitted was finalized long ago but re-entered review just now, so it
     * would sort to the top of a queue of applications that have all waited longer than it has.
     * A row with no timeline entry to measure from has no age and sorts last; `id` breaks ties, so
     * the order is stable across reads.
     *
     * @param  list<array{application: Application, age_days: ?int}>  $aged
     * @return list<array{application: Application, age_days: ?int}>
     */
    private function oldestFirst(array $aged): array
    {
        usort($aged, fn (array $a, array $b): int => [$b['age_days'] ?? -1, $a['application']->getKey()]
            <=> [$a['age_days'] ?? -1, $b['application']->getKey()]);

        return $aged;
    }

    /**
     * The oldest few waiting Applications as Card rows — named and aged, so a reviewer can start
     * with the most urgent. The applicant's name travels in both languages, never picked
     * server-side, and `age_days` is null only when there is no timeline entry to measure from.
     *
     * @param  list<array{application: Application, age_days: ?int}>  $aged
     * @return list<array<string, mixed>>
     */
    private function queueRows(array $aged, ReviewSla $sla): array
    {
        return array_map(function (array $row) use ($sla): array {
            $application = $row['application'];

            return [
                'id' => (int) $application->getKey(),
                'status' => $application->status->value,
                'name_en' => $application->name_en,
                'name_ar' => $application->name_ar,
                'age_days' => $row['age_days'],
                'is_overdue' => $sla->isOverdue($row['age_days']),
                'review_entered_at' => $application->enteredStatusAt($sla->status)?->toIso8601String(),
            ];
        }, $aged);
    }
}
