<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;

final readonly class StudentStandingScope
{
    public function __construct(
        private StudentDirectory $students,
        private Directory $directory,
        private ProgramCatalog $programs,
        private DeanAssignments $deans,
        private DepartmentHeadAssignments $heads,
    ) {}

    public function permits(int $userId, StudentSummary $student): bool
    {
        if ($student->userId === $userId || $this->directory->isAdministrator($userId)
            || $this->directory->hasRole($userId, Role::StudentAffairs)
            || $this->directory->hasRole($userId, Role::Council)) {
            return true;
        }
        if ($this->directory->hasRole($userId, Role::Advisor)) {
            return in_array($student->studentId, $this->students->studentIdsAdvisedBy($userId), true);
        }
        $program = $this->programs->find($student->programId);
        if ($program === null) {
            return false;
        }

        return ($this->directory->hasRole($userId, Role::Dean)
                && in_array($program->facultyId, $this->deans->facultiesForDean($userId), true))
            || ($this->directory->hasRole($userId, Role::DepartmentHead)
                && in_array($userId, $this->heads->headsForDepartment($program->departmentId), true));
    }
}
