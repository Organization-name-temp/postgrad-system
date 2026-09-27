<?php

namespace Support\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Applies already-authorized stored fields; domain scope belongs to the caller. */
final class SqlCollectionQuery
{
    /**
     * @template T of Model
     *
     * @param  Builder<T>  $builder
     * @param  array<string, string>  $columns  Trusted field-to-column mapping.
     * @param  list<string>  $searchColumns  Trusted column names.
     * @return Builder<T>
     */
    public static function apply(Builder $builder, CollectionQuery $query, array $columns, array $searchColumns, bool $sort = true): Builder
    {
        foreach ($query->filters as $field => $values) {
            if ($values === [] || ! isset($columns[$field])) {
                continue;
            }
            if (str_ends_with($field, '_min')) {
                $builder->where($columns[$field], '>=', $values[0]);
            } elseif (str_ends_with($field, '_max')) {
                $builder->where($columns[$field], '<=', $values[0]);
            } else {
                $builder->whereIn($columns[$field], $values);
            }
        }
        if ($query->search !== null && $searchColumns !== []) {
            $needle = ArabicNormalizer::normalize($query->search);
            $builder->where(function (Builder $search) use ($searchColumns, $needle): void {
                foreach ($searchColumns as $column) {
                    $wrapped = $search->getQuery()->getGrammar()->wrap($column);
                    // strpos gives literal substring matching, including % and _, and
                    // the folds mirror ArabicNormalizer on both operands.
                    $search->orWhereRaw("strpos(regexp_replace(translate(lower(coalesce({$wrapped}::text, '')), 'أإآٱىة', 'اااايه'), '[ؐ-ًؚ-ٰٟۖ-ۭ]', '', 'g'), ?) > 0", [$needle]);
                }
            });
        }
        if ($sort) {
            foreach ($query->sorts as $order) {
                if (isset($columns[$order['field']])) {
                    $builder->orderBy($columns[$order['field']], $order['direction']);
                }
            }
        }

        return $builder;
    }
}
