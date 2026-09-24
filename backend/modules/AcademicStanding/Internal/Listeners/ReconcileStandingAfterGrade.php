<?php

namespace Modules\AcademicStanding\Internal\Listeners;

use Modules\AcademicStanding\Internal\Support\StandingReconciler;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Public\Events\GradeFinalized;

final readonly class ReconcileStandingAfterGrade
{
    public function __construct(private EnrollmentRecords $enrollments, private TerminationCases $terminationCases, private StandingReconciler $reconciler) {}

    public function handle(GradeFinalized $event): void
    {
        $studentIds = array_values(array_unique(array_map(
            fn ($enrollment): int => $enrollment->studentId,
            $this->enrollments->findManyEnrollments($event->enrollmentIds),
        )));
        foreach ($studentIds as $studentId) {
            $this->terminationCases->reconcile($studentId);
            $this->reconciler->reconcile($studentId, 'grade_sheet', $event->gradeSheetId);
        }
    }
}
