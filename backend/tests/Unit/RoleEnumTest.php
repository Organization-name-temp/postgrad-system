<?php

use Modules\Identity\Public\Enums\Role;

// DB-free: pins the enum to the SRS §3.1 codes/names with independent literals.
it('defines exactly the ten SRS roles', function () {
    expect(collect(Role::cases())->map->value->all())
        ->toBe(['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08', 'R09', 'R10']);
});

it('maps role codes to their SRS §3.1 names', function () {
    expect(Role::from('R03')->label())->toBe('Academic Advisor')
        ->and(Role::from('R05')->label())->toBe('Student Affairs Staff')
        ->and(Role::from('R08')->label())->toBe('Education & Academic Affairs Council');
});
