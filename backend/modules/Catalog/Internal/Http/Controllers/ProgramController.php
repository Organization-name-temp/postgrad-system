<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Enums\CourseCategory;
use Modules\Catalog\Internal\Enums\DegreeLevel;
use Modules\Catalog\Internal\Http\Resources\ProgramResource;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Internal\Versioning\CatalogVersionRecorder;

/**
 * @group Catalog
 *
 * Manage Programs — degree offerings (MSc/PhD) under a Department, each carrying
 * its required credit hours per Course Category (Preparatory / Program Core /
 * Research Elective; defaults 6/6/9, total 21). Reading requires the catalog.view
 * permission; creating/editing/activating requires catalog.manage (Department
 * Head, Dean, or an administrator). Programs are deactivated, never deleted, and
 * deactivating never cascades (BR-18; ADR-0010).
 */
class ProgramController
{
    public function __construct(private readonly CatalogVersionRecorder $versions) {}

    /**
     * List Programs.
     *
     * @response 200 {"data": [{"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return ProgramResource::collection(Program::query()->orderBy('code')->get());
    }

    /**
     * Create a Program.
     *
     * The per-Category credit hours are optional; each omitted one falls back to
     * the bylaw default (Preparatory 6 / Program Core 6 / Research Elective 9).
     *
     * @bodyParam department_id integer required The owning Department id. Example: 1
     * @bodyParam code string required Unique short code for the Program. Example: MSC-CE
     * @bodyParam name_en string required English name. Example: MSc in Computer Engineering
     * @bodyParam name_ar string required Arabic name. Example: ماجستير في هندسة الحاسبات
     * @bodyParam degree_level string required One of MSc, PhD. Example: MSc
     * @bodyParam prep_required_ch integer Required Preparatory credit hours (default 6). Example: 6
     * @bodyParam core_required_ch integer Required Program Core credit hours (default 6). Example: 6
     * @bodyParam elective_required_ch integer Required Research Elective credit hours (default 9). Example: 9
     *
     * @response 201 {"data": {"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": true}}
     * @response 422 {"message": "The code has already been taken.", "errors": {"code": ["The code has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = array_merge($this->defaultCreditHours(), $request->validate($this->rules()));

        $program = Program::create($validated);
        $this->versions->recordProgram($program, 'created');

        return (new ProgramResource($program))->response()->setStatusCode(201);
    }

    /**
     * Show a Program.
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": true}}
     */
    public function show(Program $program): ProgramResource
    {
        return new ProgramResource($program);
    }

    /**
     * Update a Program.
     *
     * Any omitted per-Category credit hour keeps its current value (unlike create,
     * which fills in the bylaw default).
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @bodyParam department_id integer required The owning Department id. Example: 1
     * @bodyParam code string required Unique short code for the Program. Example: MSC-CE
     * @bodyParam name_en string required English name. Example: MSc in Computer Engineering
     * @bodyParam name_ar string required Arabic name. Example: ماجستير في هندسة الحاسبات
     * @bodyParam degree_level string required One of MSc, PhD. Example: MSc
     * @bodyParam prep_required_ch integer Required Preparatory credit hours. Example: 6
     * @bodyParam core_required_ch integer Required Program Core credit hours. Example: 6
     * @bodyParam elective_required_ch integer Required Research Elective credit hours. Example: 9
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": true}}
     */
    public function update(Request $request, Program $program): ProgramResource
    {
        $validated = $request->validate($this->rules($program));

        $program->update($validated);
        $this->versions->recordProgram($program, 'updated');

        return new ProgramResource($program);
    }

    /**
     * Activate a Program.
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": true}}
     */
    public function activate(Program $program): ProgramResource
    {
        $program->update(['is_active' => true]);
        $this->versions->recordProgram($program, 'activated');

        return new ProgramResource($program);
    }

    /**
     * Deactivate a Program.
     *
     * Deactivating retires a Program without deleting it and with no cascading
     * effect — students already enrolled and historical records are unaffected
     * (BR-18).
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسبات", "degree_level": "MSc", "prep_required_ch": 6, "core_required_ch": 6, "elective_required_ch": 9, "total_required_ch": 21, "is_active": false}}
     */
    public function deactivate(Program $program): ProgramResource
    {
        $program->update(['is_active' => false]);
        $this->versions->recordProgram($program, 'deactivated');

        return new ProgramResource($program);
    }

    /**
     * Validation rules for creating/editing a Program. On update, the code's
     * uniqueness check ignores the Program being edited. The per-Category credit
     * hours are optional (create fills defaults; update keeps existing).
     *
     * @return array<string, mixed>
     */
    private function rules(?Program $program = null): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($program?->getKey())],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'degree_level' => ['required', Rule::enum(DegreeLevel::class)],
            // Credit hours are small non-negative integers; max:255 is a defensive
            // sanity bound (a real category is single digits) that also keeps the
            // value well within the unsignedSmallInteger column, so an absurd input
            // is a 422 rather than a DB overflow 500.
            'prep_required_ch' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'core_required_ch' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'elective_required_ch' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }

    /**
     * The bylaw default credit-hour split (6/6/9) sourced from the CourseCategory
     * enum, applied on create for any per-Category value the client omits.
     *
     * @return array<string, int>
     */
    private function defaultCreditHours(): array
    {
        return [
            'prep_required_ch' => CourseCategory::Preparatory->defaultRequiredCh(),
            'core_required_ch' => CourseCategory::ProgramCore->defaultRequiredCh(),
            'elective_required_ch' => CourseCategory::ResearchElective->defaultRequiredCh(),
        ];
    }
}
