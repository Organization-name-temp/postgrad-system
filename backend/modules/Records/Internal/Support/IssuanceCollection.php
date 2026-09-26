<?php

namespace Modules\Records\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;

final readonly class IssuanceCollection
{
    public function __construct(private StudentDirectory $students, private RecordsAccess $access) {}

    /** @param array<string, mixed> $input */
    public function query(array $input): CollectionQuery
    {
        return CollectionQuery::from($input, knownFilters: ['id', 'student_id', 'status', 'reference'],
            readableFilters: ['id', 'student_id', 'status', 'reference'],
            sortable: ['id', 'student_id', 'status', 'reference', 'issued_at', 'issuer_name'], integerFilters: ['id', 'student_id']);
    }

    /** @return list<array<string, mixed>> */
    public function rows(int $actor, CollectionQuery $query): array
    {
        $rows = [];
        foreach (DB::table('transcript_issuances')->orderBy('id')->get() as $issuance) {
            $student = $this->students->findByStudentId($issuance->student_id);
            if ($student === null || ! $this->access->reads($actor, $student)) {
                continue;
            }
            $status = DB::table('transcript_revocations')->where('issuance_id', $issuance->id)->exists() ? 'Revoked' : 'Valid';
            $row = ['id' => $issuance->id, 'student_id' => $issuance->student_id, 'reference' => $issuance->reference,
                'status' => $status, 'issuer_name' => $issuance->issuer_name, 'issued_at' => $issuance->issued_at];
            foreach ($query->filters as $field => $values) {
                if ($values !== [] && ! in_array((string) $row[$field], $values, true)) {
                    continue 2;
                }
            }
            $search = implode(' ', $row).' '.$student->universityId.' '.($student->identity->nameEn ?? '').' '.($student->identity->nameAr ?? '');
            if ($query->search !== null && ! str_contains(ArabicNormalizer::normalize($search), ArabicNormalizer::normalize($query->search))) {
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
}
