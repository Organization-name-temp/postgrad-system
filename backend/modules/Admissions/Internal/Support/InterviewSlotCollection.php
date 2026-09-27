<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

final class InterviewSlotCollection
{
    private const COLUMNS = ['id' => 'id', 'interviewer_id' => 'interviewer_id', 'scheduled_at' => 'scheduled_at',
        'scheduled_at_min' => 'scheduled_at', 'scheduled_at_max' => 'scheduled_at', 'duration_minutes' => 'duration_minutes',
        'duration_minutes_min' => 'duration_minutes', 'duration_minutes_max' => 'duration_minutes', 'mode' => 'mode', 'location' => 'location'];

    public function query(Request $request, int $defaultPerPage = 25): CollectionQuery
    {
        $request->validate(['scheduled_at_min' => ['sometimes', 'date'], 'scheduled_at_max' => ['sometimes', 'date'],
            'duration_minutes_min' => ['sometimes', 'integer', 'min:1'], 'duration_minutes_max' => ['sometimes', 'integer', 'min:1'],
            'is_open' => ['sometimes', 'regex:/^\s*(true|false|1|0)(\s*,\s*(true|false|1|0))*\s*$/']]);
        $input = $request->query();
        $input['sort'] ??= 'scheduled_at';
        if (isset($input['is_open'])) {
            $input['is_open'] = implode(',', array_map(static fn (string $value): string => in_array(trim($value), ['true', '1'], true) ? '1' : '0', explode(',', $input['is_open'])));
        }
        $filters = ['id', 'interviewer_id', 'mode', 'is_open', 'scheduled_at_min', 'scheduled_at_max', 'duration_minutes_min', 'duration_minutes_max'];

        return CollectionQuery::from($input, $filters, $filters, ['id', 'interviewer_id', 'mode', 'scheduled_at', 'duration_minutes', 'location'],
            defaultPerPage: $defaultPerPage, integerFilters: ['id', 'interviewer_id']);
    }

    /** @return Builder<InterviewSlot> */
    public function selection(CollectionQuery $query, bool $onlyOpen = false): Builder
    {
        $builder = InterviewSlot::query()->with('interview');
        if ($onlyOpen) {
            $builder->open();
        }
        $open = array_unique($query->filters['is_open'] ?? []);
        if (count($open) === 1) {
            if ($open[0] === '1') {
                $builder->open();
            } else {
                $builder->whereHas('interview');
            }
        }

        return SqlCollectionQuery::apply($builder, $query, self::COLUMNS, ['location']);
    }
}
