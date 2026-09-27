<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Support\Query\CollectionQuery;

final readonly class AcademicCohort
{
    public function __construct(
        private StudentDirectory $students,
        private Directory $directory,
        private ProgramCatalog $programs,
        private DeanAssignments $deans,
        private DepartmentHeadAssignments $heads,
    ) {}

    public function select(int $actorUserId, CollectionQuery $query): AcademicCohortSelection
    {
        $global = $this->directory->isAdministrator($actorUserId)
            || $this->directory->hasRole($actorUserId, Role::StudentAffairs)
            || $this->directory->hasRole($actorUserId, Role::Council);
        $advisor = $this->directory->hasRole($actorUserId, Role::Advisor);
        $dean = $this->directory->hasRole($actorUserId, Role::Dean);
        $head = $this->directory->hasRole($actorUserId, Role::DepartmentHead);
        abort_unless($global || $advisor || $dean || $head, 403);
        $advisees = $advisor ? array_fill_keys($this->students->studentIdsAdvisedBy($actorUserId), true) : [];
        $faculties = $dean ? $this->deans->facultiesForDean($actorUserId) : [];
        $departments = $head ? $this->heads->departmentsForHead($actorUserId) : [];
        $selected = [];
        $programs = [];
        foreach (array_chunk($this->students->studentIdsMatching($query), 100) as $batch) {
            $students = $this->students->findManyByStudentId($batch);
            $programIds = array_values(array_unique(array_map(fn ($student): int => $student->programId, $students)));
            $programs += $this->programs->findMany($programIds);
            foreach ($students as $studentId => $student) {
                $program = $programs[$student->programId] ?? null;
                $permitted = $global || isset($advisees[$studentId])
                    || ($program !== null && (in_array($program->facultyId, $faculties, true)
                        || in_array($program->departmentId, $departments, true)));
                if ($permitted && $this->matchesProgram($program, $query)) {
                    $selected[$studentId] = $student;
                }
            }
        }

        return new AcademicCohortSelection($selected, $programs);
    }

    private function matchesProgram(?ProgramSummary $program, CollectionQuery $query): bool
    {
        foreach (['faculty_id', 'department_id', 'degree_level'] as $field) {
            $values = $query->filters[$field] ?? [];
            if ($values === []) {
                continue;
            }
            if ($program === null) {
                return false;
            }
            $actual = match ($field) {
                'faculty_id' => (string) $program->facultyId,
                'department_id' => (string) $program->departmentId,
                'degree_level' => $program->degreeLevel->value,
            };
            if (! in_array($actual, $values, true)) {
                return false;
            }
        }

        return true;
    }
}
