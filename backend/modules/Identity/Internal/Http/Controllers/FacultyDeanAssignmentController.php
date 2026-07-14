<?php

namespace Modules\Identity\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Internal\Http\Resources\DeanResource;
use Modules\Identity\Internal\Http\Resources\FacultyDeanAssignmentResource;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/**
 * @group Administration
 *
 * The Dean↔Faculty assignment admin surface (ticket 08) — the system's first
 * **role-gated** surface. Every endpoint sits behind the `admin` middleware, so
 * only a System/Super Administrator (R09/R10) may read or change the mapping;
 * ordinary staff — even a Dean — get a 403 (BR-75). No new permission is invented
 * for this (ADR-0008 intact): admin org-routing is a role concern, not a §3.2
 * matrix function.
 *
 * An administrator lists the current assignments, picks a Dean (an R07 user) and a
 * Faculty (opaque Catalog id, no FK — ADR-0012) to assign, or removes a row. The
 * Dean review queue (Admissions) reads the resulting mapping through the
 * Identity\Public DeanAssignments facade.
 */
class FacultyDeanAssignmentController
{
    /**
     * List Dean↔Faculty assignments.
     *
     * Every current assignment with the assigned Dean's identity and the opaque
     * Faculty id. Admin-only (R09/R10).
     *
     * @response 200 {"data": [{"id": 1, "user_id": 7, "faculty_id": 3, "dean_name": "Dr. Dean of Engineering", "dean_email": "dean.eng@ejust.edu.eg"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(): AnonymousResourceCollection
    {
        return FacultyDeanAssignmentResource::collection(
            FacultyDeanAssignment::query()->with('user')->orderBy('id')->get(),
        );
    }

    /**
     * List assignable Deans.
     *
     * The R07 (Dean) users an administrator may assign to a Faculty — the source for
     * the assignment screen's Dean picker. Admin-only (R09/R10).
     *
     * @response 200 {"data": [{"id": 7, "name": "Dr. Dean of Engineering", "email": "dean.eng@ejust.edu.eg"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function deans(): AnonymousResourceCollection
    {
        return DeanResource::collection(
            User::query()
                ->whereHas('userRoles', fn ($query) => $query->where('role', Role::Dean->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        );
    }

    /**
     * Assign a Dean to a Faculty.
     *
     * Creates one Dean↔Faculty routing row. The user must hold the Dean role (R07)
     * and the (Dean, Faculty) pair must not already exist — either fails validation
     * (422). Admin-only (R09/R10).
     *
     * @bodyParam user_id integer required The Dean (R07 user) to assign. Example: 7
     * @bodyParam faculty_id integer required The opaque Catalog Faculty id. Example: 3
     *
     * @response 201 {"data": {"id": 1, "user_id": 7, "faculty_id": 3, "dean_name": "Dr. Dean of Engineering", "dean_email": "dean.eng@ejust.edu.eg"}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 422 scenario="not a Dean" {"message": "The selected user is not a Dean.", "errors": {"user_id": ["The selected user is not a Dean."]}}
     * @response 422 scenario="already assigned" {"message": "This Dean is already assigned to that faculty.", "errors": {"faculty_id": ["This Dean is already assigned to that faculty."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'faculty_id' => [
                'required', 'integer', 'min:1',
                Rule::unique('faculty_dean_assignments', 'faculty_id')
                    ->where('user_id', $request->integer('user_id')),
            ],
        ], [
            'faculty_id.unique' => __('identity.assignment.duplicate'),
        ]);

        // The assigned user must genuinely be a Dean — routing to a non-Dean would
        // silently break the Dean queue. A domain precondition, surfaced as a 422.
        $user = User::query()->findOrFail($validated['user_id']);
        if (! $user->hasRole(Role::Dean)) {
            throw ValidationException::withMessages(['user_id' => __('identity.assignment.not_a_dean')]);
        }

        $assignment = FacultyDeanAssignment::create($validated)->load('user');

        return (new FacultyDeanAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    /**
     * Unassign a Dean from a Faculty.
     *
     * Removes one routing row. Admin-only (R09/R10).
     *
     * @urlParam assignment_id integer required The assignment row id. Example: 1
     *
     * @response 204
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No query results for model."}
     */
    public function destroy(FacultyDeanAssignment $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json(status: 204);
    }
}
