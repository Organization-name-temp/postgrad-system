<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Internal\Enrollment\UniversityIdGenerator;
use Tests\TestCase;

/*
| University-ID generation (ticket 10), unit-tested directly below the HTTP seam
| (spec §Testing Decisions). The ID is `{year}-{programCode}-{NNNN}`; {NNNN} is a
| per-(year, program) sequence starting at 0001. Touches the DB (the sequence is a
| locked counter row), so it opts into the Laravel TestCase + RefreshDatabase.
*/

uses(TestCase::class, RefreshDatabase::class);

it('formats {year}-{programCode}-{NNNN} and increments per (year, program)', function () {
    $generator = new UniversityIdGenerator;

    expect($generator->next(2026, 'MSC-CE', 10))->toBe('2026-MSC-CE-0001');
    expect($generator->next(2026, 'MSC-CE', 10))->toBe('2026-MSC-CE-0002');
});

it('runs an independent sequence for each program and year', function () {
    $generator = new UniversityIdGenerator;

    // Bucketed by (year, program id) — a different program restarts at 0001…
    expect($generator->next(2026, 'MSC-CE', 10))->toBe('2026-MSC-CE-0001');
    expect($generator->next(2026, 'PHD-CE', 11))->toBe('2026-PHD-CE-0001');

    // …and so does a different year for the same program.
    expect($generator->next(2027, 'MSC-CE', 10))->toBe('2027-MSC-CE-0001');
    expect($generator->next(2026, 'MSC-CE', 10))->toBe('2026-MSC-CE-0002');
});
