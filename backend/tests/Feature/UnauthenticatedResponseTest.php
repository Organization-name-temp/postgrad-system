<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Models\ApplicationDocument;

uses(RefreshDatabase::class);

/*
| App-wide shape of an authentication failure. This is an API-only backend with no
| `login` route, so an unauthenticated request to an auth-guarded route must be a
| clean 401 JSON — never a redirect to `login`. A bare browser navigation (e.g.
| opening a document link) carries `Accept: text/html` and no XHR header, which is
| exactly the case that previously threw "Route [login] not defined." (ADR-0016).
*/

it('answers an unauthenticated non-JSON API request with 401 JSON, not a login redirect', function () {
    $response = test()->get('/api/v1/application', ['Accept' => 'text/html']);

    $response->assertUnauthorized();
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('answers a bare document-link navigation with 401 JSON when unauthenticated', function () {
    // The reported symptom: a document URL opened as a plain browser navigation.
    $document = ApplicationDocument::factory()->create();

    $response = test()->get(
        route('admissions.documents.download', ['document' => $document->ulid]),
        ['Accept' => 'text/html'],
    );

    $response->assertUnauthorized();
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});
