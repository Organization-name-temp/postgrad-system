<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Http\Resources\FacultyResource;
use Modules\Catalog\Internal\Models\Faculty;

/**
 * @group Catalog
 *
 * Manage Faculties — the top-level academic divisions (FoE, BAS, CSIT) at the
 * root of the catalog tree. Reading requires the catalog.view permission;
 * creating/editing/activating requires catalog.manage (Department Head, Dean, or
 * an administrator). Faculties are deactivated, never deleted (ADR-0010).
 */
class FacultyController
{
    /**
     * List Faculties.
     *
     * @response 200 {"data": [{"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return FacultyResource::collection(Faculty::query()->orderBy('code')->get());
    }

    /**
     * Create a Faculty.
     *
     * @bodyParam code string required Unique short code for the Faculty. Example: FoE
     * @bodyParam name_en string required English name. Example: Faculty of Engineering
     * @bodyParam name_ar string required Arabic name. Example: كلية الهندسة
     *
     * @response 201 {"data": {"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}}
     * @response 422 {"message": "The code has already been taken.", "errors": {"code": ["The code has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $faculty = Faculty::create($validated);

        return (new FacultyResource($faculty))->response()->setStatusCode(201);
    }

    /**
     * Show a Faculty.
     *
     * @response 200 {"data": {"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}}
     */
    public function show(Faculty $faculty): FacultyResource
    {
        return new FacultyResource($faculty);
    }

    /**
     * Update a Faculty.
     *
     * @bodyParam code string required Unique short code for the Faculty. Example: FoE
     * @bodyParam name_en string required English name. Example: Faculty of Engineering
     * @bodyParam name_ar string required Arabic name. Example: كلية الهندسة
     *
     * @response 200 {"data": {"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}}
     */
    public function update(Request $request, Faculty $faculty): FacultyResource
    {
        $validated = $request->validate($this->rules($faculty));

        $faculty->update($validated);

        return new FacultyResource($faculty);
    }

    /**
     * Activate a Faculty.
     *
     * @response 200 {"data": {"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}}
     */
    public function activate(Faculty $faculty): FacultyResource
    {
        $faculty->update(['is_active' => true]);

        return new FacultyResource($faculty);
    }

    /**
     * Deactivate a Faculty.
     *
     * Deactivating retires a Faculty without deleting it — enrolled students and
     * historical records are unaffected (BR-18).
     *
     * @response 200 {"data": {"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": false}}
     */
    public function deactivate(Faculty $faculty): FacultyResource
    {
        $faculty->update(['is_active' => false]);

        return new FacultyResource($faculty);
    }

    /**
     * Validation rules for creating/editing a Faculty. On update, the code's
     * uniqueness check ignores the Faculty being edited.
     *
     * @return array<string, mixed>
     */
    private function rules(?Faculty $faculty = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('faculties', 'code')->ignore($faculty?->getKey())],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
        ];
    }
}
