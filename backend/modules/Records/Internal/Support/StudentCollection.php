<?php

namespace Modules\Records\Internal\Support;

use Modules\Identity\Public\Contracts\StudentDirectory;
use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;

final readonly class StudentCollection
{
    public function __construct(private StudentDirectory $students, private RecordsAccess $access) {}

    /** @return list<array{id: int, university_id: string, name_en: string, name_ar: string, lifecycle_status: string}> */
    public function rows(int $actor, CollectionQuery $query): array
    {
        $rows = [];
        foreach ($this->students->findManyByStudentId($this->students->allStudentIds()) as $student) {
            if (! $this->access->reads($actor, $student)) {
                continue;
            }
            $row = ['id' => $student->studentId, 'university_id' => $student->universityId,
                'name_en' => $student->identity->nameEn ?? '', 'name_ar' => $student->identity->nameAr ?? '',
                'lifecycle_status' => $student->lifecycleStatus->value];
            foreach ($query->filters as $key => $values) {
                if ($values !== [] && ! in_array((string) $row[$key], $values, true)) {
                    continue 2;
                }
            }
            if ($query->search !== null && ! str_contains(ArabicNormalizer::normalize(implode(' ', $row)), ArabicNormalizer::normalize($query->search))) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, function (array $a, array $b) use ($query): int {
            foreach ($query->sorts as $sort) {
                $comparison = $a[$sort['field']] <=> $b[$sort['field']];
                if ($comparison !== 0) {
                    return $sort['direction'] === 'asc' ? $comparison : -$comparison;
                }
            }

            return 0;
        });

        return $rows;
    }

    /** @param array<string, mixed> $input */
    public function query(array $input): CollectionQuery
    {
        return CollectionQuery::from($input, knownFilters: ['id', 'university_id', 'lifecycle_status'],
            readableFilters: ['id', 'university_id', 'lifecycle_status'],
            sortable: ['id', 'university_id', 'name_en', 'name_ar', 'lifecycle_status'], integerFilters: ['id']);
    }
}
