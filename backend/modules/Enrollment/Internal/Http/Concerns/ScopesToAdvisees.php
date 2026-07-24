<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;

/**
 * Advisee scoping for the advisor registration surfaces (ticket 06) — the one place that
 * decides *whose* requests a reviewer may see and act on. Both the request queue and the
 * decision guard read it, so the rule lives once:
 *
 * - An **Advisor** (R03) is scoped to their **live** advisee set — resolved from
 *   {@see StudentDirectory::studentIdsAdvisedBy()} at call time, so a request follows the
 *   Student when their advisor is reassigned (spec §Further Notes).
 * - An **oversight** viewer who also holds `registration.approve` — Student Affairs (R05),
 *   and a System/Super Administrator (R09/R10) — reviews **unscoped**, since scoping them to
 *   "their advisees" would show nothing (they have none) and their remit is to unblock any
 *   request (spec user stories 52; ADR-0015 for admins).
 *
 * This concern decides visibility and the 403 only. The admin write-audit that ADR-0015
 * also requires (ticket 10) is layered on at each write site — a controller records an
 * Audit Log row when {@see IdentifiesAdministrator} reports the actor reached the write via
 * the admin override, so a Student Affairs reviewer's legitimate unscoped write stays
 * un-audited while an administrator's is accountable.
 */
trait ScopesToAdvisees
{
    /**
     * The student ids the acting user's queue is scoped to, or `null` for an unscoped
     * (oversight) viewer. An Advisor with no advisees gets an empty list (an empty queue).
     *
     * @return list<int>|null
     */
    protected function adviseeScope(int $userId, Directory $directory, StudentDirectory $students): ?array
    {
        if ($directory->isAdministrator($userId) || $directory->hasRole($userId, Role::StudentAffairs)) {
            return null;
        }

        return $students->studentIdsAdvisedBy($userId);
    }

    /**
     * Assert the acting user may act on a request for the given Student: an unscoped viewer
     * always may; an Advisor only for their own advisee, else a 403 (they cannot decide
     * another advisor's advisee even by guessing an id).
     */
    protected function authorizeAdviseeScope(int $userId, int $studentId, Directory $directory, StudentDirectory $students): void
    {
        $scope = $this->adviseeScope($userId, $directory, $students);

        if ($scope !== null && ! in_array($studentId, $scope, true)) {
            abort(403);
        }
    }
}
