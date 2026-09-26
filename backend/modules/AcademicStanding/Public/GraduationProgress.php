<?php

namespace Modules\AcademicStanding\Public;

interface GraduationProgress
{
    /** @param list<ProposedCourse> $proposedCourses */
    public function readinessForStudent(int $studentId, array $proposedCourses = []): ?GraduationReadiness;

    /** @param list<ProposedCourse> $proposedCourses */
    public function underloadEligibility(int $studentId, bool $isEnrollmentSemester, array $proposedCourses): UnderloadEligibility;
}
