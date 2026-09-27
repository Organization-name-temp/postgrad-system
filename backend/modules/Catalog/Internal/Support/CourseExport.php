<?php

namespace Modules\Catalog\Internal\Support;

use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Enums\Permission;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class CourseExport
{
    public function __construct(private CourseCollection $collection, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    /**
     * Canonical import template, independent of filter/sort permissions. Every
     * catalog viewer may read these fields; the actor still owns the list gate.
     *
     * @return list<string>
     */
    public function columns(Request $request): array
    {
        abort_unless($request->user()?->can(Permission::ViewCourseCatalog->value), 403);

        return ['id', 'department_id', 'code', 'name_en', 'name_ar', 'level', 'research_type', 'lecture_ch', 'tutorial_ch', 'lab_ch', 'total_ch', 'is_active'];
    }

    public function download(Request $request): BinaryFileResponse
    {
        $columns = $this->columns($request);
        $query = $this->collection->query($request);
        $count = 0;
        $rows = function () use ($query, &$count): \Generator {
            foreach ($this->collection->selection($query)->lazy(100) as $course) {
                $count++;
                // Spreadsheet cells have no frontend renderer (ADR-0027).
                yield [(string) $course->id, $course->department_id === null ? null : (string) $course->department_id, $course->code,
                    $course->name_en, $course->name_ar, $course->level->value,
                    $course->research_type === null ? null : __('catalog.export_values.'.$course->research_type->value),
                    $course->lecture_ch, $course->tutorial_ch, $course->lab_ch, $course->total_ch,
                    __('catalog.export_values.'.($course->is_active ? 'active' : 'inactive'))];
            }
        };
        $response = $this->xlsx->download('courses.xlsx', array_map(fn (string $column): string => __('catalog.export_columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'catalog.courses_exported', actorUserId: (int) $request->user()?->getAuthIdentifier(),
            metadata: ['resource' => 'courses', 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }
}
