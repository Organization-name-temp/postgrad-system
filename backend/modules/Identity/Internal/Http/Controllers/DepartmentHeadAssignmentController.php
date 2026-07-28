<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Internal\Http\Resources\AssignableUserResource;
use Modules\Identity\Internal\Http\Resources\DepartmentHeadAssignmentResource;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Enums\Role;

/**
 * @group Administration
 *
 * The Department-Head↔Department assignment admin surface (phase 4, ticket 02) —
 * the routing map a signed Grade Sheet travels along. Shaped and gated exactly like
 * the Dean↔Faculty surface it sits beside: every endpoint is behind the `admin`
 * middleware, so only a System/Super Administrator (R09/R10) may read or change the
 * mapping, and even a Department Head gets a 403. No new permission is invented
 * (ADR-0008 intact) — admin org-routing is a role concern, not a §3.2 matrix function.
 *
 * An administrator lists the current assignments, picks a Head (an R06 user) and a
 * scope to assign, or removes a row. Grading (ticket 03) reads the resulting mapping
 * through the Identity\Public DepartmentHeadAssignments facade.
 *
 * **The scope is nullable and NULL is a real choice.** NULL is the university-wide
 * Preparatory scope — the PRE401–404 Courses that belong to no Department (ADR-0010,
 * ADR-0023) — not "no Department yet". It is therefore `present` rather than
 * optional: an administrator states the scope, never omits it.
 *
 * Unlike the Dean surface, every write here is **audited**: which grades reach which
 * reviewer is an authorisation decision, so adding or removing a route is accountable.
 */
class DepartmentHeadAssignmentController
{
    /**
     * List Department-Head↔Department assignments.
     *
     * Every current assignment with the assigned Head's identity and the opaque
     * Department id — `null` for the university-wide Preparatory scope. Admin-only
     * (R09/R10).
     *
     * @response 200 {"data": [{"id": 1, "user_id": 6, "department_id": 3, "university_wide": false, "head_name": "Dr. Head of Computer Engineering", "head_email": "head.ce@ejust.edu.eg"}, {"id": 2, "user_id": 8, "department_id": null, "university_wide": true, "head_name": "Dr. Preparatory Coordinator", "head_email": "prep@ejust.edu.eg"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(): AnonymousResourceCollection
    {
        return DepartmentHeadAssignmentResource::collection(
            DepartmentHeadAssignment::query()->with('user')->orderBy('id')->get(),
        );
    }

    /**
     * List assignable Department Heads.
     *
     * The R06 (Department Head) users an administrator may assign to a scope — the
     * source for the assignment screen's Head picker. Admin-only (R09/R10).
     *
     * @response 200 {"data": [{"id": 6, "name": "Dr. Head of Computer Engineering", "email": "head.ce@ejust.edu.eg"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function heads(): AnonymousResourceCollection
    {
        return AssignableUserResource::collection(
            User::query()
                ->whereHas('userRoles', fn ($query) => $query->where('role', Role::DepartmentHead->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        );
    }

    /**
     * Assign a Department Head to a scope.
     *
     * Creates one Head↔Department routing row. `department_id` is required to be
     * *present* but may be `null`, which assigns the university-wide Preparatory
     * scope (the Courses that belong to no Department). The user must hold the
     * Department Head role (R06) and the (Head, scope) pair must not already exist —
     * either fails validation (422). Admin-only (R09/R10).
     *
     * @bodyParam user_id integer required The Department Head (R06 user) to assign. Example: 6
     * @bodyParam department_id integer required The opaque Catalog Department id, or null for the university-wide Preparatory scope. Example: 3
     *
     * @response 201 {"data": {"id": 1, "user_id": 6, "department_id": 3, "university_wide": false, "head_name": "Dr. Head of Computer Engineering", "head_email": "head.ce@ejust.edu.eg"}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 422 scenario="not a Department Head" {"message": "The selected user is not a Department Head.", "errors": {"user_id": ["The selected user is not a Department Head."]}}
     * @response 422 scenario="already assigned" {"message": "This Department Head is already assigned to that department.", "errors": {"department_id": ["This Department Head is already assigned to that department."]}}
     */
    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            // `present` + `nullable`: the scope is stated, never inferred from an
            // absent key, so a NULL in this table always means somebody chose the
            // university-wide scope on purpose.
            //
            // Deliberately **not** `exists:departments,id`: the Department is an opaque
            // Catalog reference and Identity does not read Catalog's tables (ADR-0012),
            // exactly as the Dean surface treats its faculty id.
            'department_id' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $userId = (int) $validated['user_id'];
        $departmentId = $validated['department_id'] === null ? null : (int) $validated['department_id'];

        // The assigned user must genuinely be a Department Head — routing to a non-Head
        // would silently strand a Department's grades. A domain precondition, surfaced
        // as a 422 (the not-a-Dean precedent).
        $user = User::query()->findOrFail($userId);
        if (! $user->hasRole(Role::DepartmentHead)) {
            throw ValidationException::withMessages(['user_id' => __('identity.assignment.not_a_head')]);
        }

        // Uniqueness is checked here rather than with Rule::unique because `nullable`
        // makes Laravel skip non-implicit rules on a null value, which would leave the
        // university-wide scope — the one scope a Head can hold only once — unguarded.
        // The query itself needs no branch: `where('department_id', null)` compiles to
        // `IS NULL`, so a Department and the university-wide scope check identically.
        $exists = DepartmentHeadAssignment::query()
            ->where('user_id', $userId)
            ->where('department_id', $departmentId)
            ->exists();

        if ($exists) {
            throw $this->duplicateScope($departmentId);
        }

        // The row and its audit entry are one write. An assignment that reached the
        // routing map without an audit row would be exactly the untraceable change the
        // ticket added the audit for, so neither lands without the other.
        //
        // The read-then-write above is not atomic on its own, so a concurrent identical
        // POST — a double-submitted dialog — still reaches `create`. The database's
        // NULLS NOT DISTINCT constraint stops the duplicate row; catching it here turns
        // the loser into the same 422 the first-past-the-post caller would have got,
        // rather than a 500.
        try {
            $assignment = DB::transaction(function () use ($userId, $departmentId, $request, $auditLogger): DepartmentHeadAssignment {
                $assignment = DepartmentHeadAssignment::create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                ]);

                $auditLogger->record(
                    action: 'department_head.assigned',
                    actorUserId: (int) $request->user()?->getAuthIdentifier(),
                    metadata: ['head_user_id' => $userId, 'department_id' => $departmentId],
                    subjectType: 'department_head_assignment',
                    subjectId: (string) $assignment->getKey(),
                );

                return $assignment;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateScope($departmentId);
        }

        return (new DepartmentHeadAssignmentResource($assignment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Unassign a Department Head from a scope.
     *
     * Removes one routing row. Admin-only (R09/R10).
     *
     * @urlParam assignment_id integer required The assignment row id. Example: 1
     *
     * @response 204
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No query results for model."}
     */
    public function destroy(
        Request $request,
        DepartmentHeadAssignment $assignment,
        AuditLogger $auditLogger,
    ): JsonResponse {
        // Removing a route can strand a Department's grades in review, so it is logged
        // with the same weight as adding one — and in the same transaction, so a
        // deletion never survives without its audit entry.
        DB::transaction(function () use ($assignment, $request, $auditLogger): void {
            $assignment->delete();

            $auditLogger->record(
                action: 'department_head.unassigned',
                actorUserId: (int) $request->user()?->getAuthIdentifier(),
                metadata: [
                    'head_user_id' => $assignment->user_id,
                    'department_id' => $assignment->department_id,
                ],
                subjectType: 'department_head_assignment',
                subjectId: (string) $assignment->getKey(),
            );
        });

        return response()->json(status: 204);
    }

    /**
     * The 422 for assigning a Head to a scope they already cover. The scope decides only
     * the wording: "already assigned to that department" would name a Department the
     * administrator never picked when the scope is the university-wide one.
     */
    private function duplicateScope(?int $departmentId): ValidationException
    {
        return ValidationException::withMessages(['department_id' => __(
            $departmentId === null
                ? 'identity.assignment.duplicate_university_wide'
                : 'identity.assignment.duplicate_head',
        )]);
    }
}
