<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\Validator;
use Modules\AcademicStanding\Public\GraduationProgress;
use Support\Query\CollectionQuery;

final readonly class GraduationReportCollection
{
    public function __construct(private AcademicCohort $cohort, private GraduationProgress $progress) {}

    /** @param array<string, mixed> $input */
    public function query(array $input): CollectionQuery
    {
        Validator::make($input, [
            'remaining_ch_min' => 'nullable|integer|min:0', 'remaining_ch_max' => 'nullable|integer|min:0',
            'cgpa_min' => 'nullable|numeric|min:0|max:4', 'cgpa_max' => 'nullable|numeric|min:0|max:4',
            'proximity' => 'nullable|in:remaining_1_5',
        ])->validate();
        $filters = ['faculty_id', 'department_id', 'program_id', 'degree_level', 'lifecycle',
            'readiness', 'blocker', 'remaining_ch_min', 'remaining_ch_max', 'cgpa_min', 'cgpa_max', 'cgpa_availability', 'proximity'];

        return CollectionQuery::from($input, $filters, $filters,
            ['student_id', 'name_en', 'name_ar', 'university_id', 'program_id', 'lifecycle', 'readiness', 'remaining_ch', 'cgpa'],
            stableKey: 'student_id', integerFilters: ['faculty_id', 'department_id', 'program_id']);
    }

    /** @return list<GraduationReportRow> */
    public function rows(int $actorUserId, CollectionQuery $query): array
    {
        $selection = $this->cohort->select($actorUserId, $query);
        $values = $this->progress->readinessForStudents(array_keys($selection->students));
        $rows = [];
        foreach ($selection->students as $studentId => $student) {
            $row = new GraduationReportRow($student, $selection->programs[$student->programId] ?? null, $values[$studentId] ?? null);
            if ($this->matches($row, $query)) {
                $rows[] = $row;
            }
        }

        if (array_intersect(['readiness', 'remaining_ch', 'cgpa'], array_column($query->sorts, 'field')) !== []) {
            usort($rows, function (GraduationReportRow $a, GraduationReportRow $b) use ($query): int {
                foreach ($query->sorts as $sort) {
                    $first = $a->sortValue($sort['field']);
                    $second = $b->sortValue($sort['field']);
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

    private function matches(GraduationReportRow $row, CollectionQuery $query): bool
    {
        $filters = $query->filters;
        if (($filters['readiness'] ?? []) !== [] && ! in_array($row->group(), $filters['readiness'], true)) {
            return false;
        }
        $readiness = $row->readiness;
        if (($filters['blocker'] ?? []) !== [] && ($readiness === null || array_intersect($filters['blocker'], array_column($readiness->blockers, 'value')) === [])) {
            return false;
        }
        if (($filters['proximity'] ?? []) !== [] && ($readiness === null || $readiness->remainingCreditHours < 1 || $readiness->remainingCreditHours > 5)) {
            return false;
        }
        if (($filters['cgpa_availability'] ?? []) !== []) {
            $availability = $readiness?->cgpa === null ? 'unavailable' : 'available';
            if (! in_array($availability, $filters['cgpa_availability'], true)) {
                return false;
            }
        }
        foreach (['remaining_ch' => $readiness?->remainingCreditHours, 'cgpa' => $readiness?->cgpa] as $field => $value) {
            foreach (['min', 'max'] as $limit) {
                $bound = $filters[$field.'_'.$limit][0] ?? null;
                if ($bound !== null && ($value === null || ($limit === 'min' ? $value < (float) $bound : $value > (float) $bound))) {
                    return false;
                }
            }
        }

        return true;
    }
}
