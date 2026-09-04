<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Internal\Http\Resources\CurriculumEntryResource;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Internal\Support\PreparatoryCurriculumCompleteness;
use Modules\Catalog\Internal\Versioning\CatalogVersionRecorder;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

/**
 * @group Catalog
 *
 * Compose a Program's Curriculum — the Curriculum Entries that place Courses into
 * the three Categories (Preparatory / Program Core / Research Elective). Reading
 * requires the catalog.view permission; adding/removing an entry requires
 * catalog.manage. Because the Category lives on the entry (not the Course), the same
 * Course may appear in several Programs under different Categories (ADR-0010).
 */
class CurriculumController
{
    public function __construct(
        private readonly CatalogVersionRecorder $versions,
        private readonly PreparatoryCurriculumCompleteness $completeness,
    ) {}

    /**
     * List a Program's Curriculum.
     *
     * Returns the Program's Curriculum Entries — each with its Category and the
     * embedded Course — ordered by ordinal (the manager's insertion order within a
     * Category, with Course code as a stable tiebreak). The front end groups them
     * into the three Category sections.
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "program_id": 1, "category": "Preparatory", "preparatory_requirement_role": "KeySkills", "ordinal": 0, "course": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Key Skills", "name_ar": "المهارات الأساسية", "level": 500, "lecture_ch": 1, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 1, "is_active": true}}], "meta": {"preparatory_requirements_complete": false, "missing_preparatory_requirement_roles": ["EnglishLanguage", "ArabicLanguage", "JapaneseLanguage", "JapaneseCulture", "DepartmentalRequirement"]}}
     */
    public function index(Program $program): AnonymousResourceCollection
    {
        $entries = $program->curriculumEntries()
            ->with('course')
            ->get()
            // Order by ordinal (insertion order within a Category), Course code as a
            // deterministic tiebreak. The zero-padding keeps the ordinal numeric in
            // the composite sort key.
            ->sortBy(fn (CurriculumEntry $entry): string => sprintf('%08d %s', $entry->ordinal, $entry->course->code))
            ->values();

        $missingRoles = $this->completeness->missingRoles($program);

        return CurriculumEntryResource::collection($entries)->additional([
            'meta' => [
                'preparatory_requirements_complete' => $missingRoles === [],
                'missing_preparatory_requirement_roles' => array_map(
                    fn (PreparatoryRequirementRole $role): string => $role->value,
                    $missingRoles,
                ),
            ],
        ]);
    }

    /**
     * Add a Course to a Program's Curriculum.
     *
     * Creates a Curriculum Entry placing the Course into the given Category. The
     * ordinal is assigned automatically (appended within the Category). A Course
     * sits at most once per Program+Category (a duplicate is a 422).
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @bodyParam course_id integer required The Course to place. Example: 1
     * @bodyParam category string required One of Preparatory, ProgramCore, ResearchElective. Example: ProgramCore
     * @bodyParam preparatory_requirement_role string The semantic role, required for Preparatory and prohibited otherwise. One of EnglishLanguage, ArabicLanguage, JapaneseLanguage, JapaneseCulture, KeySkills, DepartmentalRequirement. Example: EnglishLanguage
     *
     * @response 201 {"data": {"id": 1, "program_id": 1, "category": "Preparatory", "preparatory_requirement_role": "KeySkills", "ordinal": 0, "course": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Key Skills", "name_ar": "المهارات الأساسية", "level": 500, "lecture_ch": 1, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 1, "is_active": true}}}
     * @response 422 {"message": "This course is already in that category for this program.", "errors": {"course_id": ["This course is already in that category for this program."]}}
     */
    public function store(Request $request, Program $program): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => [
                'required', 'integer', 'exists:courses,id',
                // Unique per Program+Category: the same Course may appear under a
                // different Category or in another Program, but not twice here.
                Rule::unique('curriculum_entries', 'course_id')->where(fn ($query) => $query
                    ->where('program_id', $program->getKey())
                    ->where('category', $request->input('category'))),
            ],
            'category' => ['required', Rule::enum(CourseCategory::class)],
            'preparatory_requirement_role' => [
                'nullable',
                'required_if:category,'.CourseCategory::Preparatory->value,
                'prohibited_unless:category,'.CourseCategory::Preparatory->value,
                Rule::enum(PreparatoryRequirementRole::class),
                Rule::unique('curriculum_entries', 'preparatory_requirement_role')
                    ->where(fn ($query) => $query->where('program_id', $program->getKey())),
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $program): void {
                    if ($value !== PreparatoryRequirementRole::KeySkills->value) {
                        return;
                    }

                    $course = Course::query()->find($request->integer('course_id'));

                    if ($course?->department_id !== $program->department_id) {
                        $fail(__('catalog.key_skills_must_be_departmental'));
                    }
                },
            ],
        ], [
            'course_id.unique' => __('catalog.curriculum_duplicate'),
            'preparatory_requirement_role.required_if' => __('catalog.preparatory_role_required'),
            'preparatory_requirement_role.prohibited_unless' => __('catalog.preparatory_role_only_for_preparatory'),
            'preparatory_requirement_role.unique' => __('catalog.preparatory_role_duplicate'),
        ]);

        $maxOrdinal = $program->curriculumEntries()
            ->where('category', $validated['category'])
            ->max('ordinal');

        $entry = $program->curriculumEntries()->create([
            'course_id' => $validated['course_id'],
            'category' => $validated['category'],
            'preparatory_requirement_role' => $validated['preparatory_requirement_role'] ?? null,
            'ordinal' => $maxOrdinal === null ? 0 : $maxOrdinal + 1,
        ]);

        // Editing a Program's Curriculum is a versioned Program change (ADR-0011).
        $this->versions->recordProgram($program, 'curriculum_updated');

        return (new CurriculumEntryResource($entry->load('course')))->response()->setStatusCode(201);
    }

    /**
     * Assign a Preparatory Requirement Role.
     *
     * Corrects the semantic role of an existing Preparatory Curriculum Entry.
     * Category and Course placement are unchanged.
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     * @urlParam curriculum_entry_id integer required The ID of the curriculum entry. Example: 1
     *
     * @bodyParam preparatory_requirement_role string required One of EnglishLanguage, ArabicLanguage, JapaneseLanguage, JapaneseCulture, KeySkills, DepartmentalRequirement. Example: EnglishLanguage
     *
     * @response 200 {"data": {"id": 1, "program_id": 1, "category": "Preparatory", "preparatory_requirement_role": "KeySkills", "ordinal": 0, "course": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Key Skills", "name_ar": "المهارات الأساسية", "level": 500, "lecture_ch": 1, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 1, "is_active": true}}}
     */
    public function update(Request $request, Program $program, CurriculumEntry $curriculumEntry): CurriculumEntryResource
    {
        abort_unless($curriculumEntry->program_id === $program->getKey(), 404);

        if ($curriculumEntry->category !== CourseCategory::Preparatory) {
            throw ValidationException::withMessages([
                'preparatory_requirement_role' => [__('catalog.preparatory_role_only_for_preparatory')],
            ]);
        }

        $validated = $request->validate([
            'preparatory_requirement_role' => [
                'required',
                Rule::enum(PreparatoryRequirementRole::class),
                Rule::unique('curriculum_entries', 'preparatory_requirement_role')
                    ->where(fn ($query) => $query->where('program_id', $program->getKey()))
                    ->ignore($curriculumEntry->getKey()),
                function (string $attribute, mixed $value, \Closure $fail) use ($curriculumEntry, $program): void {
                    if ($value !== PreparatoryRequirementRole::KeySkills->value) {
                        return;
                    }

                    if ($curriculumEntry->course()->value('department_id') !== $program->department_id) {
                        $fail(__('catalog.key_skills_must_be_departmental'));
                    }
                },
            ],
        ], [
            'preparatory_requirement_role.unique' => __('catalog.preparatory_role_duplicate'),
        ]);

        $curriculumEntry->update($validated);
        $this->versions->recordProgram($program, 'curriculum_updated');

        return new CurriculumEntryResource($curriculumEntry->load('course'));
    }

    /**
     * Remove a Curriculum Entry from a Program.
     *
     * Removing an entry takes the Course out of the Program's Curriculum; the Course
     * itself is untouched (it may still sit in other Programs). The change is
     * captured in the Program's version history (ADR-0011).
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     * @urlParam curriculum_entry_id integer required The ID of the curriculum entry. Example: 1
     *
     * @response 204
     */
    public function destroy(Program $program, CurriculumEntry $curriculumEntry): Response
    {
        // Scope the entry to its Program so an id from another Program 404s rather
        // than deleting across the boundary.
        abort_unless($curriculumEntry->program_id === $program->getKey(), 404);

        $curriculumEntry->delete();

        // Removing an entry changes the Program's Curriculum — record a version
        // whose snapshot reflects the now-smaller Curriculum (ADR-0011).
        $this->versions->recordProgram($program, 'curriculum_updated');

        return response()->noContent();
    }
}
