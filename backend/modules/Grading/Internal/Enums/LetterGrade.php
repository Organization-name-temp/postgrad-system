<?php

namespace Modules\Grading\Internal\Enums;

use InvalidArgumentException;
use Modules\Grading\Internal\Models\Grade;

/**
 * The grading scale — the nine letters, their GPA points, and the percentage band each
 * covers, as a **static matrix in code** (ADR-0021, in the ADR-0008 house style already used
 * by Role, Permission, ApplicationStatus and CourseStatus). It is deliberately not config:
 * a closed university-bylaw set belongs beside the code that reads it, where its every band
 * boundary can be unit-tested exhaustively rather than trusted.
 *
 * **The bands replace SRS Appendix A's, on purpose (ADR-0021).** The appendix's bands are not
 * contiguous — 90–92.99, 80–82.99 and 70–72.99 map to no letter, so a mark of 91 is
 * unrepresentable — and FR-GRD-001.2 requires the letter to be *auto-calculated* from the
 * mark, so the mapping must be total over 0–100. Uniform five-point bands make it total, at
 * the cost of moving the minimum pass from Appendix A's 77% to **75%**. Anything quoting 77%
 * is wrong; this is a decision, not a bug.
 *
 * | A+ 4.00 95–100 | B+ 3.30 85–89.99 | C+ 2.50 75–79.99 | D+ 1.30 65–69.99 | F 0.00 below 60 |
 * | A  4.00 90–94.99 | B  3.00 80–84.99 | C  2.00 70–74.99 | D  1.00 60–64.99 |
 *
 * **P and FL are not modelled** — no postgraduate Course is Pass/Fail, and Preparatory
 * courses are letter-graded and CGPA-counted (BR-56's C+ retake rule requires it). A−/B−/C−
 * are not introduced either. All nine cases are therefore reachable.
 *
 * Grading-owned and Internal, like Enrollment's CourseStatus: the value crosses to the SPA as
 * its plain string, so no cross-module consumer names this type. The derived letter and points
 * are **frozen onto each {@see Grade} when it is recorded**, so changing this table later never
 * rewrites a grade already given.
 */
enum LetterGrade: string
{
    case APlus = 'A+';
    case A = 'A';
    case BPlus = 'B+';
    case B = 'B';
    case CPlus = 'C+';
    case C = 'C';
    case DPlus = 'D+';
    case D = 'D';
    case F = 'F';

    /** The lowest mark that earns this letter (inclusive) — the band's floor. */
    public function minimumPercentage(): float
    {
        return $this->band()[0];
    }

    /** This letter's GPA points, the multiplicand of the credit-weighted quality points. */
    public function points(): float
    {
        return $this->band()[1];
    }

    /**
     * The letter a mark earns. The mapping is **total over 0–100**: the cases are declared
     * highest band first, so the first floor the mark reaches wins and F (floor 0) catches
     * everything below 60. A mark outside 0–100 is not a grade at all and throws — callers
     * validate the range first (the API returns a 422 naming the field), so reaching here
     * with one is a programming error, not user input.
     *
     * @throws InvalidArgumentException when the mark is outside 0–100
     */
    public static function forMark(float $mark): self
    {
        if ($mark < 0.0 || $mark > 100.0) {
            throw new InvalidArgumentException("A mark must be between 0 and 100; got {$mark}.");
        }

        foreach (self::cases() as $letter) {
            if ($mark >= $letter->minimumPercentage()) {
                return $letter;
            }
        }

        // Unreachable: F's floor is 0 and the range is guarded above.
        return self::F;
    }

    /**
     * This letter's row of the ADR-0021 scale as `[inclusive floor, GPA points]` — the single
     * static matrix both answers read, so a floor and its points can never drift apart.
     *
     * @return array{0: float, 1: float}
     */
    private function band(): array
    {
        return match ($this) {
            self::APlus => [95.0, 4.00],
            self::A => [90.0, 4.00],
            self::BPlus => [85.0, 3.30],
            self::B => [80.0, 3.00],
            self::CPlus => [75.0, 2.50],
            self::C => [70.0, 2.00],
            self::DPlus => [65.0, 1.30],
            self::D => [60.0, 1.00],
            self::F => [0.0, 0.00],
        };
    }
}
