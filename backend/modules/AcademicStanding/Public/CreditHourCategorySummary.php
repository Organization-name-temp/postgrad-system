<?php

namespace Modules\AcademicStanding\Public;

final readonly class CreditHourCategorySummary
{
    public function __construct(
        public CreditHourCategory $category,
        public int $baselineRequiredCh,
        public int $adjustedRequiredCh,
        public int $attendedCh,
        public int $earnedCh,
        public int $transferredCh,
        public int $waivedCh,
        public int $exemptedCh,
        public int $satisfiedCh,
        public int $remainingCh,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'baseline_required_ch' => $this->baselineRequiredCh,
            'adjusted_required_ch' => $this->adjustedRequiredCh,
            'attended_ch' => $this->attendedCh,
            'earned_ch' => $this->earnedCh,
            'transferred_ch' => $this->transferredCh,
            'waived_ch' => $this->waivedCh,
            'exempted_ch' => $this->exemptedCh,
            'satisfied_ch' => $this->satisfiedCh,
            'remaining_ch' => $this->remainingCh,
        ];
    }
}
