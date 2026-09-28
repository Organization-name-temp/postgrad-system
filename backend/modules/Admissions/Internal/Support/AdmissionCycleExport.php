<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\AuditLogger;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class AdmissionCycleExport
{
    public function __construct(private AdmissionCycleCollection $collection, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    /** Canonical actor-readable projection, independent of collection filters.
     * @return list<string>
     */
    public function columns(Request $request): array
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        return ['id', 'intake_term', 'year', 'name_en', 'name_ar', 'opens_at', 'closes_at', 'is_active', 'is_open'];
    }

    public function download(Request $request): BinaryFileResponse
    {
        $columns = $this->columns($request);
        $query = $this->collection->query($request);
        $count = 0;
        $rows = function () use ($query, &$count): \Generator {
            foreach ($this->collection->selection($query)->lazy(100) as $cycle) {
                $count++;
                yield [(string) $cycle->id, __('admissions.cycle_export.values.'.$cycle->intake_term->value), $cycle->year,
                    $cycle->name_en, $cycle->name_ar, $cycle->opens_at->toIso8601String(), $cycle->closes_at->toIso8601String(),
                    __('admissions.cycle_export.values.'.($cycle->is_active ? 'active' : 'inactive')),
                    __('admissions.cycle_export.values.'.($cycle->is_open ? 'open' : 'closed'))];
            }
        };
        $response = $this->xlsx->download('admission-cycles.xlsx', array_map(fn (string $column): string => __('admissions.cycle_export.columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'admissions.cycles_exported', actorUserId: (int) $request->user()?->getAuthIdentifier(),
            metadata: ['resource' => 'admission-cycles', 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }
}
