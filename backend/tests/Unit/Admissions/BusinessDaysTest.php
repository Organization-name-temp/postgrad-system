<?php

use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Support\BusinessDays;

/*
| The business-days SLA helper (ticket 11): how many days have elapsed between two
| moments, counting only days that are neither a Friday nor a Saturday — the Egyptian
| weekend, with no public-holiday calendar (spec §SLAs). A pure unit worth an
| authoritative direct test rather than an HTTP round-trip; the overdue-flagging
| command derives an application's review age through it.
|
| Anchors are fixed weekdays so the assertions read unambiguously:
|   2026-07-13 is a Monday, so 2026-07-17 is a Friday and 2026-07-18 a Saturday.
*/

it('counts zero elapsed business days on the same day', function () {
    $monday = Carbon::parse('2026-07-13 10:00');

    expect(BusinessDays::between($monday, $monday->copy()))->toBe(0);
});

it('counts one business day to the next weekday', function () {
    $monday = Carbon::parse('2026-07-13 10:00');
    $tuesday = Carbon::parse('2026-07-14 10:00');

    expect(BusinessDays::between($monday, $tuesday))->toBe(1);
});

it('counts consecutive mid-week days without a weekend', function () {
    $monday = Carbon::parse('2026-07-13 10:00');
    $thursday = Carbon::parse('2026-07-16 10:00');

    // Mon→Tue→Wed→Thu: three business days elapsed, no weekend crossed.
    expect(BusinessDays::between($monday, $thursday))->toBe(3);
});

it('excludes the Egyptian Friday/Saturday weekend', function () {
    $thursday = Carbon::parse('2026-07-16 10:00');
    $sunday = Carbon::parse('2026-07-19 10:00');

    // Thu→Fri(skip)→Sat(skip)→Sun: only Sunday counts.
    expect(BusinessDays::between($thursday, $sunday))->toBe(1);
});

it('counts across a full weekend to the following week', function () {
    $thursday = Carbon::parse('2026-07-16 10:00');
    $tuesday = Carbon::parse('2026-07-21 10:00');

    // Thu→Fri(skip)→Sat(skip)→Sun→Mon→Tue: Sunday, Monday, Tuesday = three.
    expect(BusinessDays::between($thursday, $tuesday))->toBe(3);
});

it('does not count the weekend itself as elapsed business days', function () {
    $friday = Carbon::parse('2026-07-17 10:00');
    $saturday = Carbon::parse('2026-07-18 10:00');

    expect(BusinessDays::between($friday, $saturday))->toBe(0);
});

it('treats a moment before the start as zero elapsed days', function () {
    $monday = Carbon::parse('2026-07-13 10:00');
    $earlier = Carbon::parse('2026-07-10 10:00');

    expect(BusinessDays::between($monday, $earlier))->toBe(0);
});

it('ignores the time of day, counting only whole elapsed days', function () {
    // Under a day apart but crossing midnight into the next weekday still counts as
    // the one business day that has begun.
    $mondayEvening = Carbon::parse('2026-07-13 23:00');
    $tuesdayMorning = Carbon::parse('2026-07-14 01:00');

    expect(BusinessDays::between($mondayEvening, $tuesdayMorning))->toBe(1);
});
