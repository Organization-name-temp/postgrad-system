<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('keeps requests unavailable until staff configure positive EGP and bilingual instructions', function () {
    actingAsRole(Role::Student);
    $this->getJson('/api/v1/records/service')->assertOk()->assertJsonPath('data.available', false)
        ->assertJsonMissingPath('data.instructions_en');
    actingAsRole(Role::StudentAffairs);
    $this->putJson('/api/v1/records/fee', ['fee' => '125.50', 'instructions_en' => 'Fictional learning bank', 'instructions_ar' => 'بنك تعليمي وهمي'])
        ->assertOk()->assertJsonPath('data.fee', '125.50');
    $this->assertDatabaseHas('audit_log', ['action' => 'records.fee_updated']);
    $this->getJson('/api/v1/records/fee')->assertOk()->assertJsonPath('data.instructions_en', 'Fictional learning bank');
    actingAsRole(Role::Student);
    $this->getJson('/api/v1/records/service')->assertOk()->assertJsonPath('data.available', true)
        ->assertJsonPath('data.fee', '125.50')->assertJsonPath('data.currency', 'EGP')
        ->assertJsonMissingPath('data.instructions_en')->assertJsonMissingPath('data.instructions_ar');
    $this->getJson('/api/v1/records/fee')->assertForbidden();
});

it('rejects nonpositive or fractional-piastre fees and missing bilingual instructions', function (string $fee, string $ar) {
    actingAsRole(Role::StudentAffairs);
    $this->putJson('/api/v1/records/fee', ['fee' => $fee, 'instructions_en' => 'Learning only', 'instructions_ar' => $ar])->assertUnprocessable();
    $this->getJson('/api/v1/records/service')->assertOk()->assertJsonPath('data.available', false);
})->with([['0', 'تعليمي'], ['-10', 'تعليمي'], ['1.001', 'تعليمي'], ['1e2', 'تعليمي'], ['100.00', '']]);

it('refuses setup mutation to every nonstaff Role', function (Role $role) {
    actingAsRole($role);
    $this->putJson('/api/v1/records/fee', ['fee' => '100', 'instructions_en' => 'Learning only', 'instructions_ar' => 'تعليمي'])->assertForbidden();
})->with([Role::Student, Role::Advisor, Role::Dean, Role::DepartmentHead, Role::Council, Role::Instructor]);
