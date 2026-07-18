<?php

namespace Modules\Identity\Public\Contracts;

use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;

/**
 * The read facade over Identity's user directory (ticket 09). Other modules resolve
 * Users by role or by id through this contract — never the Eloquent User model, the
 * same sealed seam as {@see AuditLogger}/{@see DeanAssignments} (ADR-0002/0012) — and
 * get back {@see UserSummary} DTOs.
 *
 * Admissions is the first consumer: it lists the faculty Users eligible to interview
 * ({@see self::usersByRole()}) for Student Affairs' slot picker, and resolves the
 * assigned interviewer's display name ({@see self::findMany()}) when rendering the
 * slot pool. The concrete implementation lives in `Identity\Internal` and is bound in
 * IdentityServiceProvider.
 */
interface Directory
{
    /**
     * The Users holding the given role, ordered by name — the source for a role-based
     * picker (the interviewer dropdown, and the advisor picker a later ticket adds).
     * Empty when no User holds the role.
     *
     * @return list<UserSummary>
     */
    public function usersByRole(Role $role): array;

    /**
     * The summaries for the given user ids, keyed by id; ids with no matching User are
     * simply absent from the result. Lets a screen resolve several references (a whole
     * slot pool's interviewers) in one read instead of N single lookups.
     *
     * @param  list<int>  $userIds
     * @return array<int, UserSummary>
     */
    public function findMany(array $userIds): array;

    /**
     * Whether the given user is a System (R09) or Super (R10) Administrator — the
     * "administrator" the `admin` route-middleware already recognises (ADR-0008).
     * Identity owns what an administrator *is*; a consumer reads it here rather than
     * inspecting roles itself, so the definition stays in one place.
     *
     * Admissions is the consumer: the administrative override (ADR-0015) lets an admin
     * bypass the Dean faculty-scope and interviewer row-level guards that the permission
     * matrix does not cover. `false` for an unknown user id.
     */
    public function isAdministrator(int $userId): bool;
}
