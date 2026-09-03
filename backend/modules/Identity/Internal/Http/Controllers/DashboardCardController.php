<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Modules\Identity\Internal\Http\Middleware\EnsureAdministrator;
use Modules\Identity\Internal\Http\Resources\DashboardCardResource;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Internal\Models\UserRole;

/**
 * @group Administration
 *
 * Identity's Dashboard Card (phase 4.5, ADR-0024) — the one Card a System or Super Administrator's
 * Dashboard is *about*: running the system. It closes the phase's single deliberate exception to
 * the composition rule. R09 and R10 hold every Permission in the §3.2 matrix (ADR-0008), so a naive
 * permission-union Dashboard would stack every Card in the system onto one page; holding every
 * Permission is an **authorization fact**, not a description of an administrator's job, so an
 * administrator gets an explicit Card set instead — this Card plus the two configuration Cards.
 *
 * Read-only, living in the module that owns the data, and returning typed domain values — counts,
 * ids, action keys, ISO timestamps — rather than pre-rendered display strings: the SPA renders each
 * audit action's label bilingually from its key, exactly as Notifications does with notification
 * types.
 *
 * Gated on the **R09/R10 Role** rather than on a Permission, reusing the existing
 * {@see EnsureAdministrator} `admin` middleware that every other administration route sits behind
 * since Phase 2. No Permission is invented for it, and the §3.2 matrix is untouched.
 */
class DashboardCardController
{
    /** The three-row Card convention the shared `<DashboardCard>` is built around. */
    private const ROW_LIMIT = 3;

    /**
     * Administration Card.
     *
     * The shape of the system at a glance — how many Users exist and how many Role assignments
     * they hold between them — over the most recent Audit Log activity, linking into the
     * administration surfaces.
     *
     * The two counts are aggregate `COUNT`s, never a materialised collection counted in PHP, so the
     * Card costs two scalar queries however large the installation grows. They are deliberately two
     * different numbers: a User may hold several Roles (`multi@` holds two), which is the fact the
     * whole permission-union Dashboard rests on, so `role_assignment_count` is not a synonym for
     * `user_count` and reading them side by side is what shows an administrator that.
     *
     * The activity is a **bounded recent-activity read** of the append-only log: the newest few
     * entries, newest first, each as its action key, the acting User (id and name, `null` for an
     * entry with no actor — a failed sign-in has none), and when it happened. It is emphatically
     * **not** the Phase 7 Audit Log *Report*: there is no filter, no date range, no pagination and
     * no export, because a Card is fixed, scoped and summary while a Report is filterable,
     * parameterised and cross-cutting (spec §Glossary). Phase 7 builds the Report beside this Card,
     * not out of it.
     *
     * An empty Audit Log returns an empty list and the SPA says so in words — a fresh installation
     * with nothing recorded yet is a legitimate state, never an error.
     *
     * Gated on the R09/R10 Role by the `admin` middleware; every other role, and every
     * unauthenticated request, is refused.
     *
     * @response 200 {"data": {"user_count": 24, "role_assignment_count": 27, "recent_activity": [{"id": 91, "action": "student.admitted", "actor_user_id": 7, "actor_name": "Dr. Dean", "at": "2026-08-10T09:15:00+00:00"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function administration(): DashboardCardResource
    {
        // Newest first, with the id as the tie-break: the log is written with an explicit
        // created_at and several entries can share a second, so ordering on the timestamp alone
        // would not be stable across reads.
        $recent = AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        $actorNames = User::query()
            ->whereIn('id', $recent->pluck('actor_user_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        return new DashboardCardResource([
            'user_count' => User::query()->count(),
            'role_assignment_count' => UserRole::query()->count(),
            'recent_activity' => $recent->map(function (AuditLog $entry) use ($actorNames): array {
                $actorId = $entry->actor_user_id === null ? null : (int) $entry->actor_user_id;

                return [
                    'id' => (int) $entry->getKey(),
                    'action' => $entry->action,
                    'actor_user_id' => $actorId,
                    'actor_name' => $actorId === null ? null : $actorNames->get($actorId),
                    'at' => $entry->created_at?->toIso8601String(),
                ];
            })->values()->all(),
        ]);
    }
}
