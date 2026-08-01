<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Support\StudentGradesRecordBuilder;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;

/**
 * @group Grading
 *
 * Scoped student-grade oversight (ticket 08): the same academic record the Student sees on My
 * Grades, but scoped to legitimate reviewers. The payload itself is reused byte-for-byte from
 * ticket 04's read model; this controller adds only who may see which Student.
 */
class StudentGradesController
{
    /**
     * The students whose grades the reviewer may inspect.
     *
     * A searchable picker population for the grade-oversight screen: Advisors see their live
     * advisees; Department Heads the Students in Programs under their Departments; Deans the
     * Students in their Faculties; Instructors the Students on Offerings they teach; Student
     * Affairs, Council and administrators every Student. Each row carries the same lightweight
     * Student context the existing register-for-student picker renders.
     *
     * @response 200 {"data": [{"student_id": 5, "name": "Sara Student", "university_id": "2026-MSC-CE-0001", "enrollment_term": "Fall 2026", "degree_level": "MSc", "current_load": null, "program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc"}}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
        DeanAssignments $deans,
        DepartmentHeadAssignments $heads,
        EnrollmentRecords $enrollments,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $studentIds = $this->studentIdsInScope($userId, $students, $directory, $programs, $deans, $heads, $enrollments);
        $summaries = $students->findManyByStudentId($studentIds);

        if ($summaries === []) {
            return response()->json(['data' => []]);
        }

        [$users, $programSummaries] = $this->resolveStudentRefs($summaries, $directory, $programs);

        $rows = [];
        foreach ($studentIds as $studentId) {
            $summary = $summaries[$studentId] ?? null;
            if ($summary === null) {
                continue;
            }

            $rows[] = $this->studentContext($summary, $programSummaries, $users);
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * One Student's grade record for a scoped reviewer.
     *
     * The exact academic-record payload the Student's own My Grades page reads — CGPA,
     * Semester GPAs, superseded attempts, Incomplete deadlines and must-retake markers — but
     * for a reviewer whose role legitimately covers the target Student.
     *
     * @urlParam student_id integer required The target Student anchor's id. Example: 5
     *
     * @response 200 {"data": {"cgpa": 3.42, "semesters": [{"semester": {"id": 2, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "gpa": 3.42, "courses": [{"enrollment_id": 5, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "credit_hours": 3, "course_status": "Retake", "grade": {"mark": 62, "letter_grade": "D", "gpa_points": 1.0}, "grade_history": [], "earned": false, "superseded": false, "must_retake": true, "incomplete_deadline": null}]}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 scenario="no such student" {"message": "No such student."}
     */
    public function show(
        Request $request,
        int $student_id,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
        DeanAssignments $deans,
        DepartmentHeadAssignments $heads,
        EnrollmentRecords $enrollments,
        StudentGradesRecordBuilder $records,
    ): JsonResponse {
        $student = $students->findByStudentId($student_id);
        abort_if($student === null, 404, 'No such student.');

        $userId = (int) $request->user()?->getAuthIdentifier();
        abort_unless(
            $this->mayReadStudent($userId, $student, $students, $directory, $programs, $deans, $heads, $enrollments),
            403,
        );

        return response()->json(['data' => $records->forStudent($student->studentId)]);
    }

    /**
     * @param  array<int, StudentSummary>  $students
     * @return array{0: array<int, UserSummary>, 1: array<int, ProgramSummary>}
     */
    private function resolveStudentRefs(array $students, Directory $directory, ProgramCatalog $programs): array
    {
        $userIds = array_values(array_map(fn (StudentSummary $s): int => $s->userId, $students));
        $programIds = array_values(array_unique(array_map(fn (StudentSummary $s): int => $s->programId, $students)));

        return [$directory->findMany($userIds), $programs->findMany($programIds)];
    }

    /**
     * @param  array<int, ProgramSummary>  $programs
     * @param  array<int, UserSummary>  $users
     * @return array<string, mixed>
     */
    private function studentContext(StudentSummary $student, array $programs, array $users): array
    {
        $program = $programs[$student->programId] ?? null;

        return [
            'student_id' => $student->studentId,
            'name' => isset($users[$student->userId]) ? $users[$student->userId]->name : $student->universityId,
            'university_id' => $student->universityId,
            'enrollment_term' => $student->enrollmentTerm,
            'degree_level' => $program?->degreeLevel->value,
            'current_load' => null,
            'program' => $program === null ? null : [
                'id' => $program->id,
                'code' => $program->code,
                'name_en' => $program->nameEn,
                'name_ar' => $program->nameAr,
                'degree_level' => $program->degreeLevel->value,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function studentIdsInScope(
        int $userId,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
        DeanAssignments $deans,
        DepartmentHeadAssignments $heads,
        EnrollmentRecords $enrollments,
    ): array {
        if (
            $directory->isAdministrator($userId)
            || $directory->hasRole($userId, Role::StudentAffairs)
            || $directory->hasRole($userId, Role::Council)
        ) {
            return $students->allStudentIds();
        }

        $visible = [];

        if ($directory->hasRole($userId, Role::Advisor)) {
            foreach ($students->studentIdsAdvisedBy($userId) as $studentId) {
                $visible[$studentId] = true;
            }
        }

        if ($directory->hasRole($userId, Role::Instructor)) {
            foreach ($this->instructorStudentIds($userId, $enrollments) as $studentId) {
                $visible[$studentId] = true;
            }
        }

        $programScope = $this->programScopedStudentIds($userId, $students, $programs, $directory, $deans, $heads);
        foreach ($programScope as $studentId) {
            $visible[$studentId] = true;
        }

        $ids = array_map('intval', array_keys($visible));
        sort($ids);

        return $ids;
    }

    private function mayReadStudent(
        int $userId,
        StudentSummary $student,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
        DeanAssignments $deans,
        DepartmentHeadAssignments $heads,
        EnrollmentRecords $enrollments,
    ): bool {
        if (
            $directory->isAdministrator($userId)
            || $directory->hasRole($userId, Role::StudentAffairs)
            || $directory->hasRole($userId, Role::Council)
        ) {
            return true;
        }

        if (
            $directory->hasRole($userId, Role::Advisor)
            && in_array($student->studentId, $students->studentIdsAdvisedBy($userId), true)
        ) {
            return true;
        }

        if (
            $directory->hasRole($userId, Role::Instructor)
            && in_array($student->studentId, $this->instructorStudentIds($userId, $enrollments), true)
        ) {
            return true;
        }

        $program = $programs->find($student->programId);
        if ($program === null) {
            return false;
        }

        if (
            $directory->hasRole($userId, Role::Dean)
            && in_array($program->facultyId, $deans->facultiesForDean($userId), true)
        ) {
            return true;
        }

        if (
            $directory->hasRole($userId, Role::DepartmentHead)
            && in_array($userId, $heads->headsForDepartment($program->departmentId), true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function instructorStudentIds(int $userId, EnrollmentRecords $enrollments): array
    {
        $visible = [];
        foreach ($enrollments->offeringsTaughtBy($userId) as $offering) {
            foreach ($enrollments->gradableEnrollmentsOf($offering->id) as $enrollment) {
                $visible[$enrollment->studentId] = true;
            }
        }

        $ids = array_map('intval', array_keys($visible));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function programScopedStudentIds(
        int $userId,
        StudentDirectory $students,
        ProgramCatalog $programs,
        Directory $directory,
        DeanAssignments $deans,
        DepartmentHeadAssignments $heads,
    ): array {
        if (
            ! $directory->hasRole($userId, Role::Dean)
            && ! $directory->hasRole($userId, Role::DepartmentHead)
        ) {
            return [];
        }

        $studentIds = $students->allStudentIds();
        $summaries = $students->findManyByStudentId($studentIds);
        $programsById = $programs->findMany(array_values(array_unique(array_map(
            fn (StudentSummary $student): int => $student->programId,
            $summaries,
        ))));

        $facultyIds = $directory->hasRole($userId, Role::Dean) ? $deans->facultiesForDean($userId) : [];
        $visible = [];

        foreach ($studentIds as $studentId) {
            $student = $summaries[$studentId] ?? null;
            $program = $student === null ? null : ($programsById[$student->programId] ?? null);
            if ($program === null) {
                continue;
            }

            if (
                $directory->hasRole($userId, Role::Dean)
                && in_array($program->facultyId, $facultyIds, true)
            ) {
                $visible[] = $studentId;

                continue;
            }

            if (
                $directory->hasRole($userId, Role::DepartmentHead)
                && in_array($userId, $heads->headsForDepartment($program->departmentId), true)
            ) {
                $visible[] = $studentId;
            }
        }

        return $visible;
    }
}
