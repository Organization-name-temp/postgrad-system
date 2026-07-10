<?php

it('reports service health in the data envelope', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');
});

it('returns an unknown api route as a 404 in the message envelope', function () {
    $this->getJson('/api/v1/no-such-route')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
