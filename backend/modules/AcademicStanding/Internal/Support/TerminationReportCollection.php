<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\Validator;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Support\Query\CollectionQuery;

final readonly class TerminationReportCollection
{
    public const STATES = ['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil', 'Dismissed', 'Terminated', 'TriggerCleared', 'EvidenceChanged'];

    public function __construct(private AcademicCohort $cohort) {}

    /** @param array<string, mixed> $input */
    public function query(array $input): CollectionQuery
    {
        Validator::make($input, ['history' => 'nullable|in:include,default',
            'created_from' => 'nullable|date_format:Y-m-d', 'created_to' => 'nullable|date_format:Y-m-d'])->validate();
        $input['lifecycle'] ??= 'Active,Terminated';
        $filters = ['faculty_id', 'department_id', 'program_id', 'degree_level', 'lifecycle', 'state', 'history', 'created_from', 'created_to'];

        return CollectionQuery::from($input, $filters, $filters,
            ['case_id', 'student_id', 'name_en', 'name_ar', 'university_id', 'program_id', 'lifecycle', 'state', 'created_at', 'closed_at'],
            stableKey: 'case_id', integerFilters: ['faculty_id', 'department_id', 'program_id']);
    }

    /** @return list<TerminationReportRow> */
    public function rows(int $actorUserId, CollectionQuery $query): array
    {
        $selection = $this->cohort->select($actorUserId, $query);
        $rows = [];
        foreach (array_chunk(array_keys($selection->students), 100) as $ids) {
            $cases = TerminationCase::query()->whereIn('student_id', $ids)
                ->select(['id', 'student_id', 'state', 'created_at', 'advisor_reviewed_at', 'department_head_reviewed_at', 'council_decided_at', 'closed_at']);
            $allowed = ($query->filters['history'][0] ?? 'default') === 'include'
                ? self::STATES : ['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil', 'Terminated'];
            $cases->whereIn('state', $allowed);
            if (($query->filters['state'] ?? []) !== []) {
                $cases->whereIn('state', $query->filters['state']);
            }
            foreach (['created_from' => '>=', 'created_to' => '<='] as $filter => $operator) {
                if (isset($query->filters[$filter][0])) {
                    $cases->whereDate('created_at', $operator, $query->filters[$filter][0]);
                }
            }
            foreach ($cases->get() as $case) {
                $student = $selection->students[$case->student_id];
                $rows[] = new TerminationReportRow($student, $selection->programs[$student->programId] ?? null, $case);
            }
        }
        usort($rows, function (TerminationReportRow $a, TerminationReportRow $b) use ($query): int {
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

        return $rows;
    }
}
