<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\StandingRecords;
use Support\Query\CollectionQuery;

final readonly class StandingReportCollection
{
    public function __construct(private AcademicCohort $cohort, private StandingRecords $records) {}

    /** @param array<string, mixed> $input */
    public function query(array $input): CollectionQuery
    {
        $filters = ['faculty_id', 'department_id', 'program_id', 'degree_level', 'lifecycle', 'standing'];

        return CollectionQuery::from($input, $filters, $filters,
            ['student_id', 'name_en', 'name_ar', 'university_id', 'program_id', 'lifecycle', 'standing', 'cgpa'],
            stableKey: 'student_id', integerFilters: ['faculty_id', 'department_id', 'program_id']);
    }

    /** @return list<StandingReportRow> */
    public function rows(int $actorUserId, CollectionQuery $query): array
    {
        $selection = $this->cohort->select($actorUserId, $query);
        $values = $this->records->currentForStudents(array_keys($selection->students));
        $standingFilter = $query->filters['standing'] ?? [];
        $rows = [];
        foreach ($selection->students as $studentId => $student) {
            $standing = $values[$studentId];
            if ($standingFilter === [] || in_array($standing->standing->value, $standingFilter, true)) {
                $rows[] = new StandingReportRow($student, $selection->programs[$student->programId] ?? null, $standing);
            }
        }
        if (array_intersect(['standing', 'cgpa'], array_column($query->sorts, 'field')) !== []) {
            usort($rows, function (StandingReportRow $a, StandingReportRow $b) use ($query): int {
                foreach ($query->sorts as $sort) {
                    $first = $a->sortValue($sort['field']);
                    $second = $b->sortValue($sort['field']);
                    // Unknown academic values stay after known values in both directions.
                    if (($first === null) !== ($second === null)) {
                        return $first === null ? 1 : -1;
                    }
                    $comparison = $first <=> $second;
                    if ($comparison !== 0) {
                        return $sort['direction'] === 'asc' ? $comparison : -$comparison;
                    }
                }

                return 0;
            });
        }

        return $rows;
    }
}
