<?php

namespace Modules\Identity\Public;

use Modules\Identity\Public\Contracts\StudentEnrollment;

/**
 * The result {@see StudentEnrollment::admit()} returns to Admissions after minting a
 * Student (ADR-0013): the newly-created anchor's id, its owner, and the minted
 * University ID. Admissions uses the University ID to surface on the acceptance
 * response and to carry on the `AdmissionAccepted` domain event whose listener sends
 * the acceptance notification. It reports only what the mint *produced* — the rest of
 * the enrolment (program, advisor, term) is already in the caller's hands via the
 * command it passed. An immutable value object.
 */
final readonly class AdmittedStudent
{
    public function __construct(
        public int $studentId,
        public int $userId,
        public string $universityId,
    ) {}
}
