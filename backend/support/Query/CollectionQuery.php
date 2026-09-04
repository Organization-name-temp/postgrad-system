<?php

namespace Support\Query;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Domain-free parser for ADR-0025's flat collection query contract.
 * Modules supply both the complete known set and the actor-readable subset.
 */
final readonly class CollectionQuery
{
    /**
     * @param  array<string, list<string>>  $filters
     * @param  list<array{field: string, direction: 'asc'|'desc'}>  $sorts
     */
    private function __construct(
        public array $filters,
        public array $sorts,
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $knownFilters
     * @param  list<string>  $readableFilters
     * @param  list<string>  $sortable
     * @param  list<string>|null  $knownSortable
     * @param  list<string>  $integerFilters
     */
    public static function from(
        array $input,
        array $knownFilters,
        array $readableFilters,
        array $sortable,
        string $stableKey = 'id',
        int $defaultPerPage = 25,
        ?array $knownSortable = null,
        array $integerFilters = [],
    ): self {
        $reserved = ['q', 'sort', 'page', 'per_page'];
        $unknown = array_values(array_diff(array_keys($input), $reserved, $knownFilters));
        if ($unknown !== []) {
            throw new UnprocessableEntityHttpException(self::message(
                'support.unknown_collection_query_field',
                ['field' => $unknown[0]],
                "Unknown collection query field: {$unknown[0]}.",
            ));
        }

        $filters = [];
        foreach ($knownFilters as $field) {
            if (! in_array($field, $readableFilters, true) || ! array_key_exists($field, $input)) {
                continue;
            }
            if (! is_scalar($input[$field])) {
                $integer = in_array($field, $integerFilters, true);
                throw new UnprocessableEntityHttpException(self::message(
                    $integer ? 'support.invalid_integer_collection_filter' : 'support.invalid_collection_filter',
                    ['field' => $field],
                    $integer
                        ? "Collection query field {$field} must contain only positive integers."
                        : "Collection query field {$field} must use comma-separated scalar values.",
                ));
            }
            $raw = (string) $input[$field];
            $values = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== ''));
            if (in_array($field, $integerFilters, true)) {
                foreach ($values as $value) {
                    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]) === false) {
                        throw new UnprocessableEntityHttpException(self::message(
                            'support.invalid_integer_collection_filter',
                            ['field' => $field],
                            "Collection query field {$field} must contain only positive integers.",
                        ));
                    }
                }
            }
            $filters[$field] = $values;
        }

        $sorts = [];
        $knownSortable ??= $sortable;
        $rawSort = is_scalar($input['sort'] ?? null) ? (string) $input['sort'] : '';
        foreach (array_filter(array_map('trim', explode(',', $rawSort))) as $candidate) {
            $descending = str_starts_with($candidate, '-');
            $field = ltrim($candidate, '-');
            if (! in_array($field, $knownSortable, true)) {
                throw new UnprocessableEntityHttpException(self::message(
                    'support.unknown_sort_field',
                    ['field' => $field],
                    "Unknown sort field: {$field}.",
                ));
            }
            if (! in_array($field, $sortable, true)) {
                continue;
            }
            $sorts[] = ['field' => $field, 'direction' => $descending ? 'desc' : 'asc'];
        }
        if ($sorts === []) {
            $sorts[] = ['field' => $stableKey, 'direction' => 'asc'];
        } elseif (! in_array($stableKey, array_column($sorts, 'field'), true)) {
            $sorts[] = ['field' => $stableKey, 'direction' => 'asc'];
        }

        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($input['per_page'] ?? $defaultPerPage)));
        $search = isset($input['q']) && is_scalar($input['q']) ? trim((string) $input['q']) : null;

        return new self($filters, $sorts, $search !== '' ? $search : null, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function auditCriteria(): array
    {
        return array_filter([
            ...$this->filters,
            'q' => $this->search,
            'sort' => array_map(static fn (array $sort): string => ($sort['direction'] === 'desc' ? '-' : '').$sort['field'], $this->sorts),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /** @param array<string, string> $replace */
    private static function message(string $key, array $replace, string $fallback): string
    {
        return app()->bound('translator') ? __($key, $replace) : $fallback;
    }
}
