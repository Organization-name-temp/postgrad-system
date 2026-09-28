<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Http\Request;
use Modules\Admissions\Internal\Enums\InterviewCollectionScope;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\Directory;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class InterviewExport
{
    public function __construct(private InterviewCollection $collection, private Directory $directory, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    public function download(Request $request, InterviewCollectionScope $scope): BinaryFileResponse
    {
        // The canonical readable projection is independent of query allowlists.
        abort_unless($request->user() !== null, 401);
        if ($scope === InterviewCollectionScope::Booked) {
            abort_unless($request->user()->can('interview.schedule'), 403);
        }
        $columns = ['id', 'application_id', 'name_en', 'name_ar', 'national_id', 'degree', 'status', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'interviewer_id', 'interviewer_name', 'evaluation_score', 'evaluation_notes', 'completed_at'];
        $query = $this->collection->query($request, $scope);
        $selection = $this->collection->selection($request, $query, $scope);
        $count = 0;
        $rows = function () use ($selection, &$count): \Generator {
            foreach ($selection->lazy(100)->chunk(100) as $batch) {
                $staff = $this->directory->findMany($batch->pluck('slot.interviewer_id')->filter()->unique()->values()->all());
                foreach ($batch as $interview) {
                    $slot = $interview->slot;
                    $applicant = $interview->application;
                    $count++;
                    yield [(string) $interview->id, (string) $interview->application_id,
                        $applicant?->name_en, $applicant?->name_ar, $applicant?->national_id, $applicant?->applying_for_degree?->value,
                        __('admissions.interview_states.'.$interview->status->value), $slot?->scheduled_at?->toIso8601String(),
                        $slot?->duration_minutes, $slot === null ? null : __('admissions.interview_modes.'.$slot->mode->value),
                        $slot?->location, $slot === null ? null : (string) $slot->interviewer_id,
                        ($staff[$slot?->interviewer_id] ?? null)?->name, $interview->evaluation_score, $interview->evaluation_notes,
                        $interview->completed_at?->toIso8601String()];
                }
            }
        };
        $resource = $scope === InterviewCollectionScope::Assigned ? 'interviews/assigned' : 'interviews';
        $filename = $scope === InterviewCollectionScope::Assigned ? 'assigned-interviews.xlsx' : 'interviews.xlsx';
        $response = $this->xlsx->download($filename, array_map(fn (string $column): string => __('admissions.interview_export_columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'admissions.interviews_exported', actorUserId: (int) $request->user()->getAuthIdentifier(),
            metadata: ['resource' => $resource, 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }
}
