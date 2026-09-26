<?php

use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

it('parses flat filters, total sorting, and capped pagination', function () {
    $query = CollectionQuery::from([
        'status' => 'PendingAdvisor,Returned',
        'sort' => '-submitted_at,status',
        'page' => '2',
        'per_page' => '1000',
    ], knownFilters: ['status', 'student_id'], readableFilters: ['status'], sortable: ['submitted_at', 'status'], stableKey: 'id');

    expect($query->filters)->toBe(['status' => ['PendingAdvisor', 'Returned']])
        ->and($query->sorts)->toBe([
            ['field' => 'submitted_at', 'direction' => 'desc'],
            ['field' => 'status', 'direction' => 'asc'],
            ['field' => 'id', 'direction' => 'asc'],
        ])
        ->and($query->page)->toBe(2)
        ->and($query->perPage)->toBe(100);
});

it('ignores known unreadable filters and rejects unknown fields', function () {
    $query = CollectionQuery::from(
        ['student_id' => '9'],
        knownFilters: ['status', 'student_id'],
        readableFilters: ['status'],
        sortable: ['status'],
    );

    expect($query->filters)->toBe([]);

    expect(fn () => CollectionQuery::from(
        ['secret' => 'yes'],
        knownFilters: ['status'],
        readableFilters: ['status'],
        sortable: ['status'],
    ))->toThrow(UnprocessableEntityHttpException::class);
});

it('ignores known unreadable sorts while rejecting unknown sorts', function () {
    $query = CollectionQuery::from(
        ['sort' => '-private_score'],
        knownFilters: [],
        readableFilters: [],
        sortable: ['status'],
        knownSortable: ['status', 'private_score'],
    );

    expect($query->sorts)->toBe([['field' => 'id', 'direction' => 'asc']]);

    expect(fn () => CollectionQuery::from(
        ['sort' => 'not_a_field'],
        knownFilters: [],
        readableFilters: [],
        sortable: ['status'],
    ))->toThrow(UnprocessableEntityHttpException::class);
});

it('normalizes Arabic search text without changing Latin identifiers', function () {
    expect(ArabicNormalizer::normalize('أَحْمَد'))->toBe('احمد')
        ->and(ArabicNormalizer::normalize('PRE-401'))->toBe('pre-401');
});

it('validates every value of declared positive-integer filters', function () {
    $query = CollectionQuery::from(
        ['student_id' => '10, 12'],
        knownFilters: ['student_id'],
        readableFilters: ['student_id'],
        sortable: [],
        integerFilters: ['student_id'],
    );

    expect($query->filters)->toBe(['student_id' => ['10', '12']]);

    foreach (['10,nope', '0', '-1', '1.5', (string) PHP_INT_MAX.'0'] as $invalid) {
        expect(fn () => CollectionQuery::from(
            ['student_id' => $invalid],
            knownFilters: ['student_id'],
            readableFilters: ['student_id'],
            sortable: [],
            integerFilters: ['student_id'],
        ))->toThrow(UnprocessableEntityHttpException::class);
    }

    expect(fn () => CollectionQuery::from(
        ['student_id' => ['10']],
        knownFilters: ['student_id'],
        readableFilters: ['student_id'],
        sortable: [],
        integerFilters: ['student_id'],
    ))->toThrow(UnprocessableEntityHttpException::class);
});
