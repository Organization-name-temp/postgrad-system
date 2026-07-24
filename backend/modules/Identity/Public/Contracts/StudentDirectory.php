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

    /**
     * The Student anchor with the given id, or `null` when none matches. Enrollment reads
     * this to resolve the Student behind a Registration Request (whose `student_id` is the
     * anchor id, not the owning user's) when an Advisor reviews it (ticket 06).
     */
    public function findByStudentId(int $studentId): ?StudentSummary;

    /**
     * The Student summaries for the given anchor ids, keyed by student id; ids with no
     * matching Student are absent. Lets the advisor request queue resolve every advisee's
     * Student in one read instead of N calls to {@see self::findByStudentId()}.
     *
     * @param  list<int>  $studentIds
     * @return array<int, StudentSummary>
     */
    public function findManyByStudentId(array $studentIds): array;

    /**
     * The ids of the Students currently advised by the given advisor user — the live
     * advisee set the Enrollment advisor queue and decision scope-guard read (ticket 06).
     * Resolved from the students' `advisor_id` at call time, never cached, so a request
     * follows the Student when their advisor is reassigned: reassign an advisee and they
     * appear in the new advisor's set on the next read, and leave the old one's. Empty when
     * the user advises nobody (or is not an advisor).
     *
     * @return list<int>
     */
    public function studentIdsAdvisedBy(int $advisorUserId): array;

    /**
     * The ids of every Student anchor, ordered by id — the unscoped population the
     * register-for-student picker offers Student Affairs / an administrator (ticket 09),
     * the mirror of {@see self::studentIdsAdvisedBy()} for a reviewer who is not scoped to
     * advisees. Feeds {@see self::findManyByStudentId()}, so the picker resolves in one read.
     *
     * @return list<int>
     */
    public function allStudentIds(): array;
}
