<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

final class AdmissionCycleCollection
{
    private const COLUMNS = ['id' => 'id', 'intake_term' => 'intake_term', 'year' => 'year', 'year_min' => 'year', 'year_max' => 'year',
        'name_en' => 'name_en', 'name_ar' => 'name_ar', 'is_active' => 'is_active', 'opens_at' => 'opens_at', 'closes_at' => 'closes_at'];

    public function query(Request $request, int $defaultPerPage = 25): CollectionQuery
    {
        $request->validate([
            'year_min' => ['sometimes', 'integer', 'min:1'], 'year_max' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'regex:/^\s*(true|false|1|0)(\s*,\s*(true|false|1|0))*\s*$/'],
            'is_open' => ['sometimes', 'regex:/^\s*(true|false|1|0)(\s*,\s*(true|false|1|0))*\s*$/'],
        ]);
        $input = $request->query();
        $input['sort'] ??= '-year,intake_term';
        foreach (['is_active', 'is_open'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = implode(',', array_map(static fn (string $value): string => in_array(trim($value), ['true', '1'], true) ? '1' : '0', explode(',', $input[$field])));
            }
        }
        $filters = ['id', 'intake_term', 'year', 'year_min', 'year_max', 'is_active', 'is_open'];

        return CollectionQuery::from($input, $filters, $filters, ['id', 'intake_term', 'year', 'name_en', 'name_ar', 'is_active', 'opens_at', 'closes_at'],
            defaultPerPage: $defaultPerPage, integerFilters: ['id', 'year']);
    }

    /** @return Builder<AdmissionCycle> */
    public function selection(CollectionQuery $query, bool $onlyOpen = false): Builder
    {
        $builder = AdmissionCycle::query();
        if ($onlyOpen) {
            $builder->currentlyOpen();
        }
        $open = array_unique($query->filters['is_open'] ?? []);
        if (count($open) === 1) {
            if ($open[0] === '1') {
                $builder->currentlyOpen();
            } else {
                $builder->whereNot(fn (Builder $window) => $window->currentlyOpen());
            }
        }

        return SqlCollectionQuery::apply($builder, $query, self::COLUMNS, ['name_en', 'name_ar']);
    }
}
