<?php

namespace Modules\AcademicStanding\Public;

final readonly class CreditHourSummary
{
    /** @param array<string, CreditHourCategorySummary> $rows */
    public function __construct(
        public int $studentId,
        public int $curriculumVersionId,
        public array $rows,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'curriculum_version_id' => $this->curriculumVersionId,
            'categories' => array_values(array_map(
                fn (CreditHourCategorySummary $row): array => $row->toArray(),
                array_filter($this->rows, fn (CreditHourCategorySummary $row): bool => $row->category !== CreditHourCategory::Total),
            )),
            'total' => $this->rows[CreditHourCategory::Total->value]->toArray(),
        ];
    }
}
