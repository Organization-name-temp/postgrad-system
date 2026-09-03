<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesStudent;
use Modules\Enrollment\Internal\Http\Concerns\ScopesToAdvisees;
use Modules\Enrollment\Internal\Http\Resources\DashboardCardResource;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;

/**
 * @group Enrollment
 *
 * Enrollment's Dashboard Cards (phase 4.5, ADR-0024) — the registration loop from both ends: the
 * Student's own load and open window, and the Advisor's queue of requests waiting on them. Each is
 * **read-only**, lives in the module that owns the data, is **independently authorized** by its own
 * route gate, and returns typed domain values (numbers, ids, enum values, ISO dates) rather than
 * pre-rendered display strings: the SPA renders every period name bilingually from the payload,
 * exactly as Notifications does.
 *
 * A Card introduces no domain logic of its own. Period state is read from ADR-0018's derived
 * windows, the credit-hour bounds from the existing validator's configuration, and the advisee
 * scope from the one trait the advisor queue itself uses — nothing here forms its own opinion
 * about any of them, so a Card can never disagree with the surface it links into.
 */
class DashboardCardController
{
    use ResolvesStudent;
    use ScopesToAdvisees;

    /** The three-row Card convention the shared `<DashboardCard>` is built around. */
    private const ROW_LIMIT = 3;

    /**
     * My Registration Card.
     *
     * The acting Student's current-semester registration at a glance: the credit hours they are
     * carrying with the **6–12 bounds alongside them** — so the number is anchored rather than
     * left to be judged — the period that is currently open, and the date it closes. The SPA sends
     * the Student to whichever surface that period actually allows, so the Dashboard never
     * offers an action the server would refuse.
     *
     * The Semester is the one **running** now, or — before the term starts — the one open for
     * registration, which is the Semester a Student is registering into. Period state is read from
     * ADR-0018's derived windows and never from a stored flag; the bounds come from the existing
     * {@see CreditHoursValidator}'s configuration, so the Card cannot disagree with what the cart
     * will enforce. `period_ends_at` is null when no window is open (`closed`), because that is a
     * period rather than a window and giving it a closing date would be a fiction.
     *
     * Gated on `courses.register_self` (§3.2, Student only) and scoped to the acting user's own
     * Student anchor — there is no id parameter, so another Student's load is unreachable from it.
     *
     * The no-semester-live case is described above rather than given its own `@response` scenario: a
     * second 200 whose body is a *different shape* — a null Semester where the first has an object —
     * makes Scribe emit a union response type, and the generated Orval client then cannot read
     * either branch.
     *
     * @responseField meta object required Card metadata (empty for this Card).
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "current_period": "add_drop", "period_ends_at": "2026-09-14T00:00:00+00:00", "credit_hours": 9, "min_credit_hours": 6, "max_credit_hours": 12, "enrollment_count": 3}, "meta": {}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function myRegistration(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
    ): DashboardCardResource {
        $student = $this->actingStudent($request, $students);
        $limits = CreditHoursValidator::fromConfig();

        // The term the Student is in, or the one they are registering into before it starts.
        $semester = $calendar->running() ?? $calendar->openForRegistration();

        if ($semester === null) {
            return new DashboardCardResource([
                'semester' => null,
                'current_period' => null,
                'period_ends_at' => null,
                'credit_hours' => 0,
                'min_credit_hours' => $limits->minCreditHours,
                'max_credit_hours' => $limits->maxCreditHours,
                'enrollment_count' => 0,
            ]);
        }

        $active = Enrollment::query()
            ->where('student_id', $student->studentId)
            ->active()
            ->whereHas('offering', fn ($query) => $query->where('semester_id', $semester->id));

        $now = now();

        return new DashboardCardResource([
            // The Semester's identity only — deliberately not the schedule screen's full payload,
            // which carries all eight window dates a Card has no use for.
            'semester' => [
                'id' => $semester->id,
                'term' => $semester->term->value,
                'year' => $semester->year,
                'name_en' => $semester->nameEn,
                'name_ar' => $semester->nameAr,
            ],
            // Both read from the Semester's own period authority (ADR-0018), so the Card never
            // decides for itself which window is live or which date ends it.
            'current_period' => $semester->currentPeriod($now),
            'period_ends_at' => $semester->currentPeriodEndsAt($now)?->toIso8601String(),
            // Aggregate SUM/COUNT over the schedule, not a materialised collection counted in PHP.
            'credit_hours' => (int) $active->clone()->sum('credit_hours'),
            'min_credit_hours' => $limits->minCreditHours,
            'max_credit_hours' => $limits->maxCreditHours,
            'enrollment_count' => $active->clone()->count(),
        ]);
    }

    /**
     * Advisee Requests Card.
     *
     * The registration requests waiting on the acting reviewer: how many are Pending, how many
     * advisees they hold, and the oldest few by student name — linking into the advisor queue,
     * which until now was discoverable only by opening it.
     *
     * Scoped by the one trait the queue itself uses ({@see ScopesToAdvisees}): an **Advisor** sees
     * only their **live** advisee set, resolved at call time, so the Card follows advisor
     * reassignment exactly as the queue does; **Student Affairs** and an administrator review
     * unscoped, since scoping them to "their advisees" would show nothing and their remit is to
     * unblock any request. `advisee_count` is therefore null for an unscoped reviewer — they hold
     * no advisees, which is not the same fact as holding none.
     *
     * Gated on `registration.approve` (§3.2 — Advisor / Student Affairs).
     *
     * The empty queue is described above rather than given its own `@response` scenario: an empty
     * `requests` list beside a populated one makes Scribe emit a union response type the generated
     * client cannot read.
     *
     * @responseField meta object required Card metadata (empty for this Card).
     *
     * @response 200 {"data": {"pending_count": 2, "advisee_count": 7, "requests": [{"id": 1, "student_id": 5, "student_name": "Sara Student", "university_id": "2026-MSC-CE-0001", "course_count": 2, "submitted_at": "2026-08-02T09:00:00+00:00"}]}, "meta": {}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function adviseeRequests(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
    ): DashboardCardResource {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $scope = $this->adviseeScope($userId, $directory, $students);

        $query = RegistrationRequest::query()->pending();
        if ($scope !== null) {
            $query->whereIn('student_id', $scope);
        }

        // The count is an aggregate over the whole queue; only the rows are materialised.
        $pendingCount = $query->clone()->count();

        /** @var Collection<int, RegistrationRequest> $oldest */
        $oldest = $query->clone()
            ->withCount('lines')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return new DashboardCardResource([
            'pending_count' => $pendingCount,
            'advisee_count' => $scope === null ? null : count($scope),
            'requests' => $this->requestRows($oldest, $students, $directory),
        ]);
    }

    /**
     * The oldest few requests as Card rows — the Student named, so the count becomes actionable
     * rather than abstract. Names come from the Identity {@see Directory} (a Student anchor carries
     * none, ADR-0012), resolved in one batched read across the rows rather than one per row, and
     * falling back to the University ID when the owning user cannot be resolved.
     *
     * @param  Collection<int, RegistrationRequest>  $requests
     * @return list<array<string, mixed>>
     */
    private function requestRows(Collection $requests, StudentDirectory $students, Directory $directory): array
    {
        if ($requests->isEmpty()) {
            return [];
        }

        $summaries = $students->findManyByStudentId(
            $requests->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
        $users = $directory->findMany(array_values(array_map(
            fn ($summary): int => $summary->userId,
            $summaries,
        )));

        return $requests->map(function (RegistrationRequest $registration) use ($summaries, $users): array {
            $student = $summaries[(int) $registration->student_id] ?? null;
            $user = $student === null ? null : ($users[$student->userId] ?? null);

            return [
                'id' => (int) $registration->getKey(),
                'student_id' => (int) $registration->student_id,
                'student_name' => $user !== null ? $user->name : $student?->universityId,
                'university_id' => $student?->universityId,
                'course_count' => (int) $registration->getAttribute('lines_count'),
                'submitted_at' => $registration->created_at->toIso8601String(),
            ];
        })->values()->all();
    }
}
