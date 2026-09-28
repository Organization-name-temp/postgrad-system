<?php

namespace Modules\Admissions\Internal\Support;

use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;

/** Shared screening/read scope; a Permission alone does not authorize a row. */
final readonly class ApplicationAccess
{
    public function __construct(private Directory $directory, private DeanAssignments $deans, private ProgramCatalog $catalog) {}

    /** @return list<int>|null Null denotes university-wide SA/admin review. */
    public function reviewerFacultyIds(int $actor): ?array
    {
        if ($this->directory->isAdministrator($actor) || $this->directory->hasRole($actor, Role::StudentAffairs)) {
            return null;
        }

        return $this->directory->hasRole($actor, Role::Dean) ? $this->deans->facultiesForDean($actor) : [];
    }

    public function reviews(int $actor, Application $application): bool
    {
        $faculties = $this->reviewerFacultyIds($actor);
        if ($faculties === null) {
            return true;
        }
        if ($faculties === [] || $application->primary_program_id === null) {
            return false;
        }
        $program = $this->catalog->find($application->primary_program_id);

        return $program !== null && in_array($program->facultyId, $faculties, true);
    }

    public function reads(int $actor, Application $application): bool
    {
        return $actor === (int) $application->user_id || $this->reviews($actor, $application);
    }
}
