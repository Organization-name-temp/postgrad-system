<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Http\Request;
use Support\Query\CollectionQuery;

final class StaffPickerQuery
{
    public static function from(Request $request): CollectionQuery
    {
        $input = $request->query();
        $input['sort'] ??= 'name';

        return CollectionQuery::from($input, ['id'], ['id'], ['id', 'name', 'email'], defaultPerPage: 100, integerFilters: ['id']);
    }
}
