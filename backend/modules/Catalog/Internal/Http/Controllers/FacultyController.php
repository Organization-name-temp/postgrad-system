<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Http\Resources\FacultyResource;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Support\ReferenceCollection;
use Modules\Catalog\Internal\Support\ReferenceExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * @queryParam q string Bilingual name or code fragment. Example: A
     * @queryParam code string Exact codes. Example: A001
     * @queryParam is_active string Active states. Example: true
     * @queryParam sort string Comma-separated readable fields; minus descends. Example: code,-name_en
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size capped at 100. Example: 100
     *
     * @response 200 {"data": [{"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true}], "links": {"first": "http://localhost/api/v1/faculties?page=1", "last": "http://localhost/api/v1/faculties?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 100, "total": 1, "last_page": 1}}
     */
    public function index(Request $request, ReferenceCollection $collection): AnonymousResourceCollection
    {
        $query = $collection->query($request, 'faculties');

        return FacultyResource::collection($collection->selection(Faculty::query(), $query, 'faculties')->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString());
    }

    /**
     * Export Faculties.
     *
     * All matching authorized rows with canonical localized columns; paging is ignored.
     *
     * @queryParam q string Bilingual name or code fragment. Example: A
     * @queryParam code string Exact codes. Example: A001
     * @queryParam is_active string Active states. Example: true
     * @queryParam sort string Comma-separated readable fields; minus descends. Example: code,-name_en
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size capped at 100. Example: 100
     *
     * @response 200 <<binary>>
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function export(Request $request, ReferenceExport $export): BinaryFileResponse
    {
        return $export->download($request, 'faculties', Faculty::class);
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
     * @urlParam faculty_id integer required The ID of the faculty. Example: 1
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
     * @urlParam faculty_id integer required The ID of the faculty. Example: 1
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
     * @urlParam faculty_id integer required The ID of the faculty. Example: 1
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
     * @urlParam faculty_id integer required The ID of the faculty. Example: 1
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
