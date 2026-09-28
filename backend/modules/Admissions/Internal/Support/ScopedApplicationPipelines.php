<?php

namespace Modules\Admissions\Internal\Support;

use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Public\ApplicationPipelines;
use Modules\Admissions\Public\ApplicationPipelineSummary;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Support\Query\CollectionQuery;

final readonly class ScopedApplicationPipelines implements ApplicationPipelines
{
    public function __construct(private ApplicationCollection $collection, private Directory $directory) {}

    public function summarize(int $actorUserId, int $admissionCycleId, ?int $programId = null, ?string $degreeLevel = null): ApplicationPipelineSummary
    {
        abort_unless($this->directory->isAdministrator($actorUserId) || $this->directory->hasRole($actorUserId, Role::StudentAffairs) || $this->directory->hasRole($actorUserId, Role::Dean), 403);
        $criteria = ['admission_cycle_id' => $admissionCycleId];
        if ($programId !== null) {
            $criteria['program_id'] = $programId;
        }
        if ($degreeLevel !== null) {
            $criteria['degree_level'] = $degreeLevel;
        }
        $query = CollectionQuery::from($criteria, ['admission_cycle_id', 'program_id', 'degree_level'], ['admission_cycle_id', 'program_id', 'degree_level'], ['id']);
        $counts = array_fill_keys(array_column(ApplicationStatus::cases(), 'value'), 0);
        $entered = $counts;
        $crossings = [];
        $total = 0;
        foreach ($this->collection->rows($actorUserId, $query, ApplicationStatus::cases()) as $application) {
            $total++;
            $counts[$application->status->value]++;
            $visited = [$application->status->value => true];
            $converted = [];
            foreach ($application->statusTransitions as $transition) {
                $visited[$transition->to_status->value] = true;
                if ($transition->from_status !== null) {
                    $visited[$transition->from_status->value] = true;
                    $converted[$transition->from_status->value.':'.$transition->to_status->value] = true;
                }
            }
            foreach (array_keys($visited) as $status) {
                $entered[$status]++;
            }
            // A returned/resubmitted loop counts an Application once per edge.
            foreach (array_keys($converted) as $edge) {
                $crossings[$edge] = ($crossings[$edge] ?? 0) + 1;
            }
        }
        $conversions = [];
        foreach (ApplicationStatus::cases() as $from) {
            foreach ($from->allowedTransitions() as $to) {
                $eligible = $entered[$from->value];
                $converted = $crossings[$from->value.':'.$to->value] ?? 0;
                $conversions[] = ['from_status' => $from->value, 'to_status' => $to->value, 'eligible' => $eligible, 'converted' => $converted,
                    'rate' => $eligible === 0 ? null : round($converted / $eligible * 100, 2)];
            }
        }

        return new ApplicationPipelineSummary($total, $counts, $conversions);
    }
}
