<?php

namespace Modules\Identity\Internal\Enrollment;

use Modules\Identity\Internal\Models\StudentNumberSequence;

/**
 * Mints the University ID stamped on a Student at acceptance (ticket 10, ADR-0013):
 * `{year}-{programCode}-{NNNN}`, where `{NNNN}` is a zero-padded per-(year, program)
 * sequence starting at 0001. Two applicants admitted to the same program in the same
 * intake year get 0001, 0002, …; a different program or year restarts the count.
 *
 * The sequence lives in a counter row ({@see StudentNumberSequence}) rather than being
 * derived by counting Students, so it can be locked: {@see self::next()} reads the row
 * `FOR UPDATE`, so when it runs inside the acceptance transaction (as it always does)
 * two concurrent admissions into the same bucket serialise and can never mint the same
 * number. The UNIQUE(year, program_id) index backs a first-ever-row insert race.
 */
class UniversityIdGenerator
{
    /**
     * The next University ID for the given intake year and program, advancing the
     * per-(year, program) counter by one. `$programCode` is Catalog's own code
     * (passed in by Admissions — Identity never reads Catalog), used verbatim in the
     * ID; `$programId` is the opaque reference the counter is bucketed by.
     */
    public function next(int $year, string $programCode, int $programId): string
    {
        $sequence = StudentNumberSequence::query()
            ->where('year', $year)
            ->where('program_id', $programId)
            ->lockForUpdate()
            ->first()
            ?? new StudentNumberSequence(['year' => $year, 'program_id' => $programId, 'next_number' => 1]);

        $number = $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        return sprintf('%d-%s-%04d', $year, $programCode, $number);
    }
}
