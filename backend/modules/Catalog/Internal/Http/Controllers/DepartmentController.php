<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Http\Resources\DepartmentResource;
use Modules\Catalog\Internal\Models\Department;

/**
 * @group Catalog
 *
 * Manage Departments — academic units within a Faculty. Reading requires the
 * catalog.view permission; creating/editing/activating requires catalog.manage
 * (Department Head, Dean, or an administrator). Departments are deactivated,
 * never deleted (ADR-0010).
 */
class DepartmentController
{
    /**
     * List Departments.
     *
     * @response 200 {"data": [{"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return DepartmentResource::collection(Department::query()->orderBy('code')->get());
    }

    /**
     * Create a Department.
     *
     * @bodyParam faculty_id integer required The owning Faculty id. Example: 1
     * @bodyParam code string required Unique short code for the Department. Example: CE
     * @bodyParam name_en string required English name. Example: Computer Engineering
     * @bodyParam name_ar string required Arabic name. Example: هندسة الحاسبات
     *
     * @response 201 {"data": {"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}}
     * @response 422 {"message": "The code has already been taken.", "errors": {"code": ["The code has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $department = Department::create($validated);

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    /**
     * Show a Department.
     *
     * @response 200 {"data": {"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}}
     */
    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department);
    }

    /**
     * Update a Department.
     *
     * @bodyParam faculty_id integer required The owning Faculty id. Example: 1
     * @bodyParam code string required Unique short code for the Department. Example: CE
     * @bodyParam name_en string required English name. Example: Computer Engineering
     * @bodyParam name_ar string required Arabic name. Example: هندسة الحاسبات
     *
     * @response 200 {"data": {"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}}
     */
    public function update(Request $request, Department $department): DepartmentResource
    {
        $validated = $request->validate($this->rules($department));

        $department->update($validated);

        return new DepartmentResource($department);
    }

    /**
     * Activate a Department.
     *
     * @response 200 {"data": {"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}}
     */
    public function activate(Department $department): DepartmentResource
    {
        $department->update(['is_active' => true]);

        return new DepartmentResource($department);
    }

    /**
     * Deactivate a Department.
     *
     * Deactivating retires a Department without deleting it — enrolled students and
     * historical records are unaffected (BR-18).
     *
     * @response 200 {"data": {"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": false}}
     */
    public function deactivate(Department $department): DepartmentResource
    {
        $department->update(['is_active' => false]);

        return new DepartmentResource($department);
    }

    /**
     * Validation rules for creating/editing a Department. On update, the code's
     * uniqueness check ignores the Department being edited.
     *
     * @return array<string, mixed>
     */
    private function rules(?Department $department = null): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department?->getKey())],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
        ];
    }
}
