<?php

namespace Modules\Catalog\Internal\Versioning;

use Illuminate\Support\Facades\Auth;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;

/**
 * Appends catalog version history (ADR-0011). Every create/edit/activate/deactivate
 * of a Program or Course — and every Curriculum edit, which is a Program change —
 * calls this to write one immutable snapshot row with a per-subject incrementing
 * version_no. The snapshot is the full aggregate, so a Program's Curriculum is
 * captured for free. This is Catalog-owned domain history; it deliberately does NOT
 * flow through the Identity AuditLogger (the separate security trail).
 */
class CatalogVersionRecorder
{
    /** Record a version of a Program's full aggregate (fields + curriculum). */
    public function recordProgram(Program $program, string $changeNote): CatalogVersion
    {
        return $this->append(VersionSubject::Program, $program->getKey(), $this->programSnapshot($program), $changeNote);
    }

    /** Record a version of a Course's fields. */
    public function recordCourse(Course $course, string $changeNote): CatalogVersion
    {
        return $this->append(VersionSubject::Course, $course->getKey(), $this->courseSnapshot($course), $changeNote);
    }

    /**
     * Append one snapshot row with the next version_no for this subject.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function append(VersionSubject $subject, int $subjectId, array $snapshot, string $changeNote): CatalogVersion
    {
        $nextVersion = CatalogVersion::query()
            ->where('subject_type', $subject->value)
            ->where('subject_id', $subjectId)
            ->max('version_no') + 1;

        return CatalogVersion::create([
            'subject_type' => $subject,
            'subject_id' => $subjectId,
            'version_no' => $nextVersion,
            'snapshot' => $snapshot,
            'change_note' => $changeNote,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }

    /**
     * The full Program aggregate: its scalar fields plus its Curriculum Entries
     * (course + category + ordinal). Snapshotting the whole aggregate captures
     * curriculum changes without versioning the join table separately (ADR-0011).
     *
     * @return array<string, mixed>
     */
    private function programSnapshot(Program $program): array
    {
        return [
            'code' => $program->code,
            'name_en' => $program->name_en,
            'name_ar' => $program->name_ar,
            'department_id' => $program->department_id,
            'degree_level' => $program->degree_level->value,
            'prep_required_ch' => $program->prep_required_ch,
            'core_required_ch' => $program->core_required_ch,
            'elective_required_ch' => $program->elective_required_ch,
            'total_required_ch' => $program->total_required_ch,
            'is_active' => $program->is_active,
            'curriculum' => $program->curriculumEntries()
                ->with('course')
                ->get()
                ->map(fn (CurriculumEntry $entry): array => [
                    'course_id' => $entry->course_id,
                    'course_code' => $entry->course->code,
                    'category' => $entry->category->value,
                    'ordinal' => $entry->ordinal,
                ])
                ->all(),
        ];
    }

    /**
     * The full Course aggregate — its fields (no Category; that is per-Program).
     *
     * @return array<string, mixed>
     */
    private function courseSnapshot(Course $course): array
    {
        return [
            'code' => $course->code,
            'name_en' => $course->name_en,
            'name_ar' => $course->name_ar,
            'department_id' => $course->department_id,
            'level' => $course->level->value,
            'lecture_ch' => $course->lecture_ch,
            'tutorial_ch' => $course->tutorial_ch,
            'lab_ch' => $course->lab_ch,
            'total_ch' => $course->total_ch,
            'is_active' => $course->is_active,
        ];
    }
}
