<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Catalog\Internal\Enums\CourseCategory;
use Modules\Catalog\Internal\Http\Resources\CurriculumEntryResource;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;

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
     * @response 200 {"data": [{"id": 1, "program_id": 1, "category": "ProgramCore", "ordinal": 0, "course": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}}]}
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

        return CurriculumEntryResource::collection($entries);
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
     *
     * @response 201 {"data": {"id": 1, "program_id": 1, "category": "ProgramCore", "ordinal": 0, "course": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}}}
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
        ], [
            'course_id.unique' => __('catalog.curriculum_duplicate'),
        ]);

        $maxOrdinal = $program->curriculumEntries()
            ->where('category', $validated['category'])
            ->max('ordinal');

        $entry = $program->curriculumEntries()->create([
            'course_id' => $validated['course_id'],
            'category' => $validated['category'],
            'ordinal' => $maxOrdinal === null ? 0 : $maxOrdinal + 1,
        ]);

        return (new CurriculumEntryResource($entry->load('course')))->response()->setStatusCode(201);
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

        return response()->noContent();
    }
}
