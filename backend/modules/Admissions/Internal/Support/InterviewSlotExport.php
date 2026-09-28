<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\Directory;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class InterviewSlotExport
{
    public function __construct(private InterviewSlotCollection $collection, private Directory $directory, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('interview.schedule'), 403);
        // Canonical manager-readable projection, independent of query allowlists.
        $columns = ['id', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'interviewer_id', 'interviewer_name', 'is_open'];
        $query = $this->collection->query($request);
        $count = 0;
        $rows = function () use ($query, &$count): \Generator {
            foreach ($this->collection->selection($query)->lazy(100)->chunk(100) as $batch) {
                $staff = $this->directory->findMany($batch->pluck('interviewer_id')->unique()->values()->all());
                foreach ($batch as $slot) {
                    $count++;
                    yield [(string) $slot->id, $slot->scheduled_at->toIso8601String(), $slot->duration_minutes,
                        __('admissions.interview_modes.'.$slot->mode->value), $slot->location, (string) $slot->interviewer_id,
                        ($staff[$slot->interviewer_id] ?? null)?->name,
                        __('admissions.slot_export.values.'.($slot->interview === null ? 'open' : 'booked'))];
                }
            }
        };
        $response = $this->xlsx->download('interview-slots.xlsx', array_map(fn (string $column): string => __('admissions.slot_export.columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'admissions.interview_slots_exported', actorUserId: (int) $request->user()->getAuthIdentifier(),
            metadata: ['resource' => 'interview-slots', 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }
}
