<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;

/**
 * @group Catalog
 *
 * The catalog browse tree — the whole academic hierarchy (Faculty → Department →
 * Program → Category → Course) in a single read-model payload so the front end can
 * render and search it client-side without chatty per-node requests (FR-PCM-002,
 * spec §API). Every node carries both language names; Course nodes carry their
 * active status so retired courses read accessibly. Reading requires catalog.view —
 * the catalog is universally browsable (ADR-0008). "University" is a synthetic root
 * the SPA prepends; it is not an entity, so it is not in this payload.
 */
class CatalogTreeController
{
    /**
     * The catalog browse tree.
     *
     * Faculties are ordered by code; within each Program the three Categories are
     * always present in bylaw order (Preparatory, Program Core, Research Elective)
     * with their required credit hours, and Courses within a Category follow the
     * curriculum ordinal (Course code as a stable tiebreak).
     *
     * @response 200 {"data": {"faculties": [{"id": 1, "code": "FoE", "name_en": "Faculty of Engineering", "name_ar": "كلية الهندسة", "is_active": true, "departments": [{"id": 1, "code": "CE", "name_en": "Computer Engineering", "name_ar": "هندسة الحاسوب", "is_active": true, "programs": [{"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسوب", "degree_level": "MSc", "is_active": true, "categories": [{"category": "Preparatory", "required_ch": 6, "courses": [{"id": 10, "code": "PRE401", "name_en": "English for Academic Purposes", "name_ar": "الإنجليزية للأغراض الأكاديمية", "level": 400, "is_active": true}]}]}]}]}]}}
     */
    public function index(): JsonResponse
    {
        $faculties = Faculty::query()
            ->with([
                'departments' => fn (Builder $query) => $query->orderBy('code'),
                'departments.programs' => fn (Builder $query) => $query->orderBy('code'),
                'departments.programs.curriculumEntries.course',
            ])
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => [
                'faculties' => $faculties->map($this->mapFaculty(...))->all(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function mapFaculty(Faculty $faculty): array
    {
        return [
            'id' => $faculty->getKey(),
            'code' => $faculty->code,
            'name_en' => $faculty->name_en,
            'name_ar' => $faculty->name_ar,
            'is_active' => $faculty->is_active,
            'departments' => $faculty->departments->map($this->mapDepartment(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapDepartment(Department $department): array
    {
        return [
            'id' => $department->getKey(),
            'code' => $department->code,
            'name_en' => $department->name_en,
            'name_ar' => $department->name_ar,
            'is_active' => $department->is_active,
            'programs' => $department->programs->map($this->mapProgram(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapProgram(Program $program): array
    {
        return [
            'id' => $program->getKey(),
            'code' => $program->code,
            'name_en' => $program->name_en,
            'name_ar' => $program->name_ar,
            'degree_level' => $program->degree_level->value,
            'is_active' => $program->is_active,
            'categories' => $this->mapCategories($program),
        ];
    }

    /**
     * The three Categories, always emitted in bylaw order even when empty, each with
     * the Program's required credit hours for it and its placed Courses.
     *
     * @return list<array<string, mixed>>
     */
    private function mapCategories(Program $program): array
    {
        $entriesByCategory = $program->curriculumEntries
            ->groupBy(fn (CurriculumEntry $entry): string => $entry->category->value);

        return array_map(function (CourseCategory $category) use ($program, $entriesByCategory): array {
            $courses = ($entriesByCategory->get($category->value) ?? collect())
                // Ordinal is the manager's order within a Category; Course code is a
                // deterministic tiebreak. Zero-padding keeps the ordinal numeric.
                ->sortBy(fn (CurriculumEntry $entry): string => sprintf('%08d %s', $entry->ordinal, $entry->course->code))
                ->map(fn (CurriculumEntry $entry): array => $this->mapCourse($entry->course))
                ->values()
                ->all();

            return [
                'category' => $category->value,
                'required_ch' => $this->requiredChFor($program, $category),
                'courses' => $courses,
            ];
        }, CourseCategory::cases());
    }

    /** @return array<string, mixed> */
    private function mapCourse(Course $course): array
    {
        return [
            'id' => $course->getKey(),
            'code' => $course->code,
            'name_en' => $course->name_en,
            'name_ar' => $course->name_ar,
            'level' => $course->level->value,
            'is_active' => $course->is_active,
        ];
    }

    private function requiredChFor(Program $program, CourseCategory $category): int
    {
        return match ($category) {
            CourseCategory::Preparatory => $program->prep_required_ch,
            CourseCategory::ProgramCore => $program->core_required_ch,
            CourseCategory::ResearchElective => $program->elective_required_ch,
        };
    }
}
