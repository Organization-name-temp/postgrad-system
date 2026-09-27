<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('pages normalized staff search inside the picker role and excludes directory exports', function (string $endpoint, Role $role, Role $viewer) {
    $first = User::factory()->create(['name' => 'أحمد محاور', 'remember_token' => 'private-picker-probe']);
    $first->assignRole($role);
    $second = User::factory()->create(['name' => 'احمد محاور']);
    $second->assignRole($role);
    $outside = User::factory()->create(['name' => 'احمد محاور']);
    $outside->assignRole(Role::Applicant);
    actingAsRole($viewer);
    $criteria = '?q='.urlencode('احمد').'&sort=id&per_page=1';
    $this->getJson($endpoint.$criteria)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id)->assertJsonMissingPath('data.0.remember_token');
    $this->getJson($endpoint.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.id', $second->id);
    $this->getJson($endpoint.'?id='.$outside->id)->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson($endpoint.'?q=private-picker-probe')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson($endpoint)->assertOk()->assertJsonPath('meta.per_page', 100);
    $this->getJson($endpoint.'?unknown=1')->assertUnprocessable();
    $this->getJson($endpoint.'/export')->assertNotFound();
    actingAsRole(Role::Applicant);
    $this->getJson($endpoint)->assertForbidden();
})->with([
    ['/api/v1/interview-slots/interviewers', Role::Instructor, Role::StudentAffairs],
    ['/api/v1/dean/advisors', Role::Advisor, Role::Dean],
]);
