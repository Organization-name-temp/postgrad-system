<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\CreditHourCategorySummary;

final class CreditHourSummaryCalculator
{
    /**
     * @param  array<string, int>  $baseline
     * @param  list<CreditFact>  $attended
     * @param  list<CreditFact>  $earned
     * @param  list<CreditFact>  $transferred
     * @param  list<CreditFact>  $waived
     * @param  list<CreditFact>  $exempted
     * @return array<string, CreditHourCategorySummary>
     */
    public static function calculate(
        array $baseline,
        array $attended,
        array $earned,
        array $transferred,
        array $waived,
        array $exempted,
    ): array {
        $rows = [];
        $decisionTargets = array_fill_keys(array_map(
            fn (CreditFact $fact): int => $fact->courseId,
            [...$exempted, ...$transferred, ...$waived],
        ), true);

        foreach (self::curriculumCategories() as $category) {
            $baselineCh = max(0, (int) ($baseline[$category->value] ?? 0));
            $attendedCh = self::sum($attended, $category);
            $earnedCh = self::sum($earned, $category);
            $transferredCh = self::sum($transferred, $category);
            $waivedCh = self::sum($waived, $category);
            $exemptedCh = self::sum($exempted, $category);
            $adjusted = max(0, $baselineCh - $exemptedCh);
            $eligibleEarned = self::sum(array_values(array_filter(
                $earned,
                fn (CreditFact $fact): bool => ! isset($decisionTargets[$fact->courseId]),
            )), $category);
            $satisfied = min($adjusted, $eligibleEarned + $transferredCh + $waivedCh);

            $rows[$category->value] = new CreditHourCategorySummary(
                category: $category,
                baselineRequiredCh: $baselineCh,
                adjustedRequiredCh: $adjusted,
                attendedCh: $attendedCh,
                earnedCh: $earnedCh,
                transferredCh: $transferredCh,
                waivedCh: $waivedCh,
                exemptedCh: $exemptedCh,
                satisfiedCh: $satisfied,
                remainingCh: max(0, $adjusted - $satisfied),
            );
        }

        $rows[CreditHourCategory::Total->value] = self::total($rows);

        return $rows;
    }

    /** @param list<CreditFact> $facts */
    private static function sum(array $facts, CreditHourCategory $category): int
    {
        return array_sum(array_map(
            fn (CreditFact $fact): int => $fact->category === $category ? $fact->creditHours : 0,
            $facts,
        ));
    }

    /** @param array<string, CreditHourCategorySummary> $rows */
    private static function total(array $rows): CreditHourCategorySummary
    {
        $sum = fn (string $property): int => array_sum(array_map(
            fn (CreditHourCategorySummary $row): int => $row->{$property},
            $rows,
        ));

        return new CreditHourCategorySummary(
            category: CreditHourCategory::Total,
            baselineRequiredCh: $sum('baselineRequiredCh'),
            adjustedRequiredCh: $sum('adjustedRequiredCh'),
            attendedCh: $sum('attendedCh'),
            earnedCh: $sum('earnedCh'),
            transferredCh: $sum('transferredCh'),
            waivedCh: $sum('waivedCh'),
            exemptedCh: $sum('exemptedCh'),
            satisfiedCh: $sum('satisfiedCh'),
            remainingCh: $sum('remainingCh'),
        );
    }

    /** @return list<CreditHourCategory> */
    private static function curriculumCategories(): array
    {
        return [
            CreditHourCategory::Preparatory,
            CreditHourCategory::ProgramCore,
            CreditHourCategory::ResearchElective,
        ];
    }
}
