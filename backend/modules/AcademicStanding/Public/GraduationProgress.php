<?php

namespace Modules\AcademicStanding\Public;

interface GraduationProgress
{
    /** @param list<ProposedCourse> $proposedCourses */
    public function readinessForStudent(int $studentId, array $proposedCourses = []): ?GraduationReadiness;

    /**
     * Advisory readiness with no proposed schedule. Missing curriculum summaries
     * are omitted so consumers can distinguish unavailable evidence.
     *
     * @param  list<int>  $studentIds
     * @return array<int, GraduationReadiness>
     */
    public function readinessForStudents(array $studentIds): array;

    /** @param list<ProposedCourse> $proposedCourses */
    public function underloadEligibility(int $studentId, bool $isEnrollmentSemester, array $proposedCourses): UnderloadEligibility;
}
