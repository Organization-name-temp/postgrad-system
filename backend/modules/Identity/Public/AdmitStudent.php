<?php

namespace Modules\Identity\Public;

use Modules\Identity\Public\Contracts\StudentEnrollment;

/**
 * The command Admissions hands {@see StudentEnrollment::admit()} on the terminal
 * `Accepted` transition (ADR-0013). It carries everything Identity needs to mint a
 * Student without reaching into any other module: the accepted applicant's user id,
 * the opaque Catalog Program id *and its code* (Admissions resolves the code through
 * the Catalog facade and passes it in, so Identity never depends on Catalog), the
 * intake year and term the University ID and enrollment record are stamped with, the
 * chosen advisor, and the frozen identity snapshot.
 *
 * `advisorId` is already resolved by the caller — the Dean's override or, absent one,
 * the least-loaded suggestion ({@see StudentEnrollment::suggestAdvisor()}) — and
 * validated to hold R03. `actorUserId` is the Dean who accepted, recorded as the actor
 * on the Identity audit entry the mint writes. An immutable value object.
 */
final readonly class AdmitStudent
{
    public function __construct(
        public int $userId,
        public ?int $actorUserId,
        public int $programId,
        public string $programCode,
        public int $admissionYear,
        public string $enrollmentTerm,
        public int $admissionCycleId,
        public int $advisorId,
        public StudentIdentity $identity,
    ) {}
}
