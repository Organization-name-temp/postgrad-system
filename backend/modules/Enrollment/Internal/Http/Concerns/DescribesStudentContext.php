<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;

/**
 * Builds the "student beside the request" context — identity, degree, Program, enrollment
 * term, and (on a review screen) the current semester load — shared by the Advisor review
 * surfaces ({@see RegistrationReviewController}, ticket 06) and the register-for-student
 * picker ({@see RegistrationController}, ticket 09), so the shape a reviewer/composer sees a
 * Student as lives in one place. Names come from the Identity {@see Directory} (the Student
 * anchor carries none, ADR-0012); the degree/Program from the Catalog.
 */
trait DescribesStudentContext
{
    /**
     * Resolve the owning users and Programs for a set of Student summaries in one read each.
     *
     * @param  array<int, StudentSummary>  $students
     * @return array{0: array<int, UserSummary>, 1: array<int, ProgramSummary>}
     */
    protected function resolveStudentRefs(array $students, Directory $directory, ProgramCatalog $programs): array
    {
        $userIds = array_values(array_map(fn (StudentSummary $s): int => $s->userId, $students));
        $programIds = array_values(array_unique(array_map(fn (StudentSummary $s): int => $s->programId, $students)));

        return [$directory->findMany($userIds), $programs->findMany($programIds)];
    }

    /**
     * The Student context beside a request — identity, degree, Program, enrollment term, and
     * (on the review screen) current semester load.
     *
     * @param  array<int, ProgramSummary>  $programs  keyed by program id
     * @param  array<int, UserSummary>  $users  keyed by user id
     * @return array<string, mixed>
     */
    protected function studentContext(?StudentSummary $student, array $programs, array $users, ?int $currentLoad): array
    {
        if ($student === null) {
            return [];
        }

        $program = $programs[$student->programId] ?? null;

        return [
            'student_id' => $student->studentId,
            'name' => isset($users[$student->userId]) ? $users[$student->userId]->name : $student->universityId,
            'university_id' => $student->universityId,
            'enrollment_term' => $student->enrollmentTerm,
            'degree_level' => $program?->degreeLevel->value,
            'current_load' => $currentLoad,
            'program' => $program === null ? null : [
                'id' => $program->id,
                'code' => $program->code,
                'name_en' => $program->nameEn,
                'name_ar' => $program->nameAr,
                'degree_level' => $program->degreeLevel->value,
            ],
        ];
    }
}
