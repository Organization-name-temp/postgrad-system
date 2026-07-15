<?php

namespace Modules\Identity\Public\Contracts;

use Modules\Identity\Public\StudentSummary;

/**
 * The read facade over the `Student` anchors (ADR-0013). Other modules — and
 * Identity's own `/me` payload — resolve a Student by owning user or by University ID
 * through this contract, never the Eloquent Student model, the same sealed seam as
 * {@see StudentEnrollment}/{@see Directory} (ADR-0012), and get back
 * {@see StudentSummary} DTOs.
 *
 * The concrete implementation lives in `Identity\Internal` and is bound in
 * IdentityServiceProvider.
 */
interface StudentDirectory
{
    /**
     * The Student anchor owned by the given user, or `null` when the user is not (yet)
     * a Student. Drives the `/me` student-identity payload once a user holds R02.
     */
    public function find(int $userId): ?StudentSummary;

    /**
     * The Student with the given University ID, or `null` when none matches.
     */
    public function findByUniversityId(string $universityId): ?StudentSummary;
}
