<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/**
 * ADR-0025: one shared HTTP contract, extended by dataset rows as owners adopt it.
 * Each owner supplies fixture identities and an actor, never a query implementation.
 */
it('preserves the shared collection contract inside the actor scope', function (string $endpoint, Closure $fixture) {
    $scenario = $fixture();
    Sanctum::actingAs(User::query()->findOrFail($scenario['actor']));

    $this->getJson($endpoint.'?unknown_contract_field=1')->assertUnprocessable();
    $this->getJson($endpoint.'?'.$scenario['outside_filter'])->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
    $this->getJson($endpoint.'?'.$scenario['private_probe'])->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson($endpoint.'?per_page=1')->assertOk()->assertJsonPath('meta.total', $scenario['total'])
        ->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $scenario['first'])
        ->assertJsonMissingPath('data.0.phone');
})->with('collection contracts');
