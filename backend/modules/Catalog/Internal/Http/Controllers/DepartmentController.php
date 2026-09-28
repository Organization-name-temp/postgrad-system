<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Http\Resources\DepartmentResource;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Support\ReferenceCollection;
use Modules\Catalog\Internal\Support\ReferenceExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * @queryParam q string Bilingual name or code fragment. Example: A
     * @queryParam id string Comma-separated reference ids. Example: 1,2
     * @queryParam code string Exact codes. Example: A001
     * @queryParam is_active string Active states. Example: true
     * @queryParam faculty_id string Faculty ids. Example: 1
     * @queryParam sort string Comma-separated readable fields; minus descends. Example: code,-name_en
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size capped at 100. Example: 100
     *
     * @response 200 {"data": [{"id": 1, "faculty_id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسبات", "is_active": true}], "links": {"first": "http://localhost/api/v1/departments?page=1", "last": "http://localhost/api/v1/departments?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 100, "total": 1, "last_page": 1}}
     */
    public function index(Request $request, ReferenceCollection $collection): AnonymousResourceCollection
    {
        $query = $collection->query($request, 'departments');

        return DepartmentResource::collection($collection->selection(Department::query(), $query, 'departments')->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString());
    }

    /**
     * Export Departments.
     *
     * All matching authorized rows with canonical localized columns; paging is ignored.
     *
     * @queryParam q string Bilingual name or code fragment. Example: A
     * @queryParam id string Comma-separated reference ids. Example: 1,2
     * @queryParam code string Exact codes. Example: A001
     * @queryParam is_active string Active states. Example: true
     * @queryParam faculty_id string Faculty ids. Example: 1
     * @queryParam sort string Comma-separated readable fields; minus descends. Example: code,-name_en
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size capped at 100. Example: 100
     *
     * @response 200 <<binary>>
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function export(Request $request, ReferenceExport $export): BinaryFileResponse
    {
        return $export->download($request, 'departments', Department::class);
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
     * @urlParam department_id integer required The ID of the department. Example: 1
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
     * @urlParam department_id integer required The ID of the department. Example: 1
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
     * @urlParam department_id integer required The ID of the department. Example: 1
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
     * @urlParam department_id integer required The ID of the department. Example: 1
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
