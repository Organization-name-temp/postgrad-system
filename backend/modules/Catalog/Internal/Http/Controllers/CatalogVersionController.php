<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Http\Resources\CatalogVersionResource;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;

/**
 * @group Catalog
 *
 * Read a Program's or Course's version history (ADR-0011). A catalog read, so it
 * requires catalog.view (every academic role) — a manager reads it too, since they
 * hold catalog.view. The history is read-only: there are no create/update/delete
 * routes; each version is appended automatically when the subject is mutated. This
 * is Catalog-owned domain history, distinct from the Identity Audit Log.
 */
class CatalogVersionController
{
    /**
     * List a Program's version history.
     *
     * Returns every version of the Program, oldest first, each with its snapshot of
     * the full aggregate (fields + Curriculum) at that point.
     *
     * @urlParam program_id integer required The ID of the program. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "version_no": 1, "change_note": "curriculum_updated", "changed_by": 1, "changed_at": "2026-07-12T10:00:00+00:00", "snapshot": {"code": "MSC-CE", "preparatory_requirements_complete": false, "missing_preparatory_requirement_roles": ["EnglishLanguage", "ArabicLanguage", "JapaneseLanguage", "JapaneseCulture", "DepartmentalRequirement"], "curriculum": [{"course_id": 1, "course_code": "DEP501", "category": "Preparatory", "preparatory_requirement_role": "KeySkills", "ordinal": 0}]}}]}
     */
    public function forProgram(Program $program): AnonymousResourceCollection
    {
        return CatalogVersionResource::collection($this->history(VersionSubject::Program, $program->getKey()));
    }

    /**
     * List a Course's version history.
     *
     * Returns every version of the Course, oldest first, each with its snapshot of
     * the Course's fields at that point.
     *
     * @urlParam course_id integer required The ID of the course. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "version_no": 1, "change_note": "created", "changed_by": 1, "changed_at": "2026-07-12T10:00:00+00:00", "snapshot": {"code": "DEP501", "total_ch": 3}}]}
     */
    public function forCourse(Course $course): AnonymousResourceCollection
    {
        return CatalogVersionResource::collection($this->history(VersionSubject::Course, $course->getKey()));
    }

    /**
     * The version rows for one subject, oldest first.
     *
     * @return Collection<int, CatalogVersion>
     */
    private function history(VersionSubject $subject, int $subjectId): Collection
    {
        return CatalogVersion::query()
            ->where('subject_type', $subject->value)
            ->where('subject_id', $subjectId)
            ->orderBy('version_no')
            ->get();
    }
}
