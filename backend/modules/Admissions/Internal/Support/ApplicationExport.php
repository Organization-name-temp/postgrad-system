<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class ApplicationExport
{
    public function __construct(private Directory $directory, private ProgramCatalog $programs, private ApplicationCollection $collection, private XlsxExporter $xlsx, private AuditLogger $audit) {}

    /** Canonical readable projection, deliberately independent of query allowlists.
     * @return list<string>
     */
    public function columns(int $actor): array
    {
        abort_unless($this->directory->isAdministrator($actor) || $this->directory->hasRole($actor, Role::StudentAffairs) || $this->directory->hasRole($actor, Role::Dean), 403);

        return ['id', 'name_en', 'name_ar', 'national_id', 'degree', 'program', 'status', 'age_days', 'date_of_birth', 'nationality', 'phone', 'address', 'finalized_at'];
    }

    /** @param list<ApplicationStatus> $statuses */
    public function download(Request $request, array $statuses): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $columns = $this->columns($actor);
        $query = $this->collection->query($request);
        $count = 0;
        $rows = function () use ($actor, $query, $statuses, $request, &$count): \Generator {
            $selection = LazyCollection::make(fn () => $this->collection->rows($actor, $query, $statuses));
            foreach ($selection->chunk(100) as $batch) {
                $ids = $batch->pluck('primary_program_id')->filter()->unique()->values()->all();
                $programs = $this->programs->findMany($ids);
                foreach ($batch as $application) {
                    $program = $programs[$application->primary_program_id] ?? null;
                    $application->setAttribute('resolved_primary_program', $program);
                    $row = (new ApplicationQueueResource($application))->resolve($request);
                    $count++;
                    yield [(string) $application->id, $application->name_en, $application->name_ar, $application->national_id,
                        $application->applying_for_degree?->value, $program?->code, __('admissions.statuses.'.$application->status->value),
                        $row['age_days'], $application->date_of_birth?->toDateString(), $application->nationality, $application->phone,
                        implode(', ', array_filter([$application->address_street, $application->address_city, $application->address_country])),
                        $application->finalized_at?->toIso8601String()];
                }
            }
        };
        $response = $this->xlsx->download('applications.xlsx', array_map(fn (string $column): string => __('admissions.export_columns.'.$column), $columns), $rows(), app()->getLocale() === 'ar');
        $this->audit->record(action: 'admissions.applications_exported', actorUserId: $actor,
            metadata: ['resource' => 'applications', 'criteria' => $query->auditCriteria(), 'row_count' => $count]);

        return $response;
    }
}
