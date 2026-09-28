<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;

/** Oversight grants no operational capability or evidence access. */
final readonly class TerminationReportReviewAccess
{
    private bool $advisor;

    private bool $head;

    private bool $council;

    /** @var list<int> */
    private array $departments;

    public function __construct(
        private int $actorUserId,
        Directory $directory,
        DepartmentHeadAssignments $heads,
        bool $canRecommend,
        bool $canApprove,
    ) {
        $this->advisor = $canRecommend && $directory->hasRole($actorUserId, Role::Advisor);
        $this->head = $canRecommend && $directory->hasRole($actorUserId, Role::DepartmentHead);
        $this->council = $canApprove && $directory->hasRole($actorUserId, Role::Council);
        $this->departments = $this->head ? $heads->departmentsForHead($actorUserId) : [];
    }

    public function forRow(TerminationReportRow $row): ?string
    {
        if ($row->student->lifecycleStatus !== StudentLifecycleStatus::Active) {
            return null;
        }

        return match ($row->case->state) {
            'AwaitingAdvisor' => $this->advisor && $row->student->advisorId === $this->actorUserId ? '/termination/advisor' : null,
            'AwaitingDepartmentHead' => $this->head && $row->program !== null && in_array($row->program->departmentId, $this->departments, true) ? '/termination/department-head' : null,
            'AwaitingCouncil' => $this->council ? '/termination/council' : null,
            default => null,
        };
    }
}
