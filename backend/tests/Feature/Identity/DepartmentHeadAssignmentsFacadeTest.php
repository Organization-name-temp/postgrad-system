<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;

uses(RefreshDatabase::class);

/*
| The Identity\Public DepartmentHeadAssignments read facade (phase 4, ticket 02) —
| the one way another module resolves who reviews a Department's Grade Sheets.
| Exercised through the bound contract, never Identity's Eloquent model (ADR-0012);
| Departments are opaque Catalog ids with no FK, so the facade trades in plain ints.
|
| The contract takes a **nullable** Department id, and NULL is not "no department" —
| it is the university-wide Preparatory scope, mirroring Course.department_id
| (ADR-0010). Ticket 03 routes every Sheet through this one call with no special
| case, so the NULL path is asserted here as a first-class case.
*/

function departmentHeadAssignments(): DepartmentHeadAssignments
{
    return app(DepartmentHeadAssignments::class);
}

/**
 * A genuine R06 Head assigned to the given scope — null being the university-wide one.
 * The factory mints the Head with its role, so a case reads as the one fact it is about.
 */
function assignedHead(?int $departmentId): User
{
    $assignment = $departmentId === null
        ? DepartmentHeadAssignment::factory()->universityWide()->create()
        : DepartmentHeadAssignment::factory()->create(['department_id' => $departmentId]);

    return User::query()->findOrFail($assignment->user_id);
}

it('resolves the Head assigned to a Department', function () {
    $head = assignedHead(7);

    expect(departmentHeadAssignments()->headsForDepartment(7))->toBe([$head->id]);
});

it('resolves the Head assigned to the university-wide Preparatory scope', function () {
    $head = assignedHead(null);

    expect(departmentHeadAssignments()->headsForDepartment(null))->toBe([$head->id]);
});

it('returns no Head for a Department that has none', function () {
    assignedHead(7);

    expect(departmentHeadAssignments()->headsForDepartment(8))->toBe([]);
});

it('does not answer a Department query with the university-wide Head', function () {
    // The two scopes are disjoint: a NULL assignment covers only the NULL-department
    // Preparatory Courses, and never stands in for a Department that lost its Head.
    assignedHead(null);

    expect(departmentHeadAssignments()->headsForDepartment(7))->toBe([]);
});

it('does not answer the university-wide query with a Department Head', function () {
    assignedHead(7);

    expect(departmentHeadAssignments()->headsForDepartment(null))->toBe([]);
});

it('resolves every Head covering the same Department', function () {
    $first = assignedHead(7);
    $second = assignedHead(7);

    expect(departmentHeadAssignments()->headsForDepartment(7))
        ->toEqualCanonicalizing([$first->id, $second->id]);
});

it('returns live academic Departments for a Head without extending the Preparatory assignment', function () {
    $head = assignedHead(7);
    $second = DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => 9]);
    DepartmentHeadAssignment::factory()->universityWide()->create(['user_id' => $head->id]);
    assignedHead(11);
    expect(departmentHeadAssignments()->departmentsForHead($head->id))->toBe([7, 9]);
    $second->delete();
    expect(departmentHeadAssignments()->departmentsForHead($head->id))->toBe([7]);
});
