<?php

namespace Modules\Catalog\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

/** Catalog-owned common collection selection over its reference entities. */
final class ReferenceCollection
{
    /** @return list<string> */
    private function fields(string $resource): array
    {
        return match ($resource) {
            'programs' => ['department_id', 'faculty_id', 'degree_level'],
            'departments' => ['faculty_id'],
            'faculties' => [],
            default => throw new \LogicException('Unknown Catalog reference collection.'),
        };
    }

    public function query(Request $request, string $resource): CollectionQuery
    {
        $request->validate(['is_active' => ['sometimes', 'regex:/^\s*(true|false|1|0)(\s*,\s*(true|false|1|0))*\s*$/']]);
        $filters = ['code', 'is_active', ...$this->fields($resource)];
        $sortable = ['id', 'code', 'name_en', 'name_ar', ...array_diff($filters, ['code', 'faculty_id'])];
        if ($resource === 'departments') {
            $sortable[] = 'faculty_id';
        }
        $input = $request->query();
        $input['sort'] ??= 'code';

        return CollectionQuery::from($input, $filters, $filters, $sortable, defaultPerPage: 100, integerFilters: array_values(array_intersect($filters, ['department_id', 'faculty_id'])));
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $builder  owner-supplied Catalog model
     * @return Builder<T>
     */
    public function selection(Builder $builder, CollectionQuery $query, string $resource): Builder
    {
        $fields = ['id', 'code', 'name_en', 'name_ar', 'is_active', ...$this->fields($resource)];
        $columns = array_combine($fields, $fields);
        if ($resource === 'programs') {
            unset($columns['faculty_id']);
            if (($query->filters['faculty_id'] ?? []) !== []) {
                $builder->whereHas('department', fn ($department) => $department->whereIn('faculty_id', $query->filters['faculty_id']));
            }
        }

        return SqlCollectionQuery::apply($builder, $query, $columns, ['code', 'name_en', 'name_ar']);
    }
}
