<?php

namespace Modules\Catalog\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Catalog\Internal\Models\Course;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

final class CourseCollection
{
    private const FILTERS = ['department_id', 'level', 'total_ch', 'total_ch_min', 'total_ch_max', 'research_type', 'is_active'];

    private const SORTABLE = ['id', 'code', 'name_en', 'name_ar', 'department_id', 'level', 'lecture_ch', 'tutorial_ch', 'lab_ch', 'total_ch', 'research_type', 'is_active'];

    public function query(Request $request): CollectionQuery
    {
        $request->validate([
            'level' => ['sometimes', 'regex:/^\d+(,\d+)*$/'],
            'total_ch' => ['sometimes', 'regex:/^\d+(,\d+)*$/'],
            'total_ch_min' => ['sometimes', 'integer', 'min:0'],
            'total_ch_max' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'regex:/^(true|false|1|0)(,(true|false|1|0))*$/'],
        ]);
        $input = $request->query();
        $input['sort'] ??= 'code';

        return CollectionQuery::from($input, self::FILTERS, self::FILTERS, self::SORTABLE, defaultPerPage: 100, integerFilters: ['department_id']);
    }

    /** @return Builder<Course> */
    public function selection(CollectionQuery $query): Builder
    {
        $columns = array_combine(self::SORTABLE, self::SORTABLE);
        $columns['total_ch_min'] = 'total_ch';
        $columns['total_ch_max'] = 'total_ch';

        return SqlCollectionQuery::apply(Course::query(), $query, $columns, ['code', 'name_en', 'name_ar']);
    }
}
