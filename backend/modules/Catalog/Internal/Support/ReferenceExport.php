<?php

namespace Modules\Catalog\Internal\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Enums\Permission;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class ReferenceExport
{
    public function __construct(private ReferenceCollection $collection, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    /** Actor-readable canonical projection, separate from query fields.
     * @return list<string>
     */
    public function columns(Request $request, string $resource): array
    {
        abort_unless($request->user()?->can(Permission::ViewCourseCatalog->value), 403);
        $extra = match ($resource) {
            'programs' => ['department_id', 'degree_level', 'prep_required_ch', 'core_required_ch', 'elective_required_ch', 'total_required_ch'],
            'departments' => ['faculty_id'],
            'faculties' => [],
            default => throw new \LogicException('Unknown Catalog export.'),
        };

        return ['id', 'code', 'name_en', 'name_ar', 'is_active', ...$extra];
    }

    /** @param class-string<Model> $model */
    public function download(Request $request, string $resource, string $model): BinaryFileResponse
    {
        $columns = $this->columns($request, $resource);
        $query = $this->collection->query($request, $resource);
        $count = 0;
        $rows = function () use ($resource, $model, $columns, $query, &$count): \Generator {
            foreach ($this->collection->selection($model::query(), $query, $resource)->lazy(100) as $record) {
                $count++;
                yield array_map(fn (string $column): bool|float|int|string|null => $this->cell($record, $column), $columns);
            }
        };
        $response = $this->xlsx->download($resource.'.xlsx', array_map(fn (string $column): string => __('catalog.reference_columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'catalog.'.$resource.'_exported', actorUserId: (int) $request->user()?->getAuthIdentifier(),
            metadata: ['resource' => $resource, 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }

    private function cell(Model $record, string $column): bool|float|int|string|null
    {
        $value = $record->getAttribute($column);
        if ($column === 'id' || str_ends_with($column, '_id')) {
            return $value === null ? null : (string) $value;
        }
        if ($column === 'is_active') {
            return __('catalog.export_values.'.($value ? 'active' : 'inactive'));
        }
        if ($value instanceof BackedEnum) {
            return __('catalog.export_values.'.$value->value);
        }
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new \LogicException('Unsupported canonical Catalog export cell.');
    }
}
