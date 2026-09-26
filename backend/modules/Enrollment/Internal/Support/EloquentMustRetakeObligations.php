<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Public\MustRetakeObligations;
use Modules\Enrollment\Public\MustRetakeObligationSummary;

final class EloquentMustRetakeObligations implements MustRetakeObligations
{
    public function activeForStudent(int $studentId): array
    {
        return MustRetakeObligation::query()->where('student_id', $studentId)->where('state', 'Active')
            ->orderByRaw("CASE WHEN category = 'Preparatory' THEN 0 ELSE 1 END")
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (MustRetakeObligation $item): MustRetakeObligationSummary => $this->summary($item))->all();
    }

    /** @param list<int> $enrollmentIds */
    public function reconcileEnrollmentIds(array $enrollmentIds): void
    {
        $enrollments = Enrollment::query()->with('offering')->whereKey($enrollmentIds)->orderBy('id')->get();
        foreach ($enrollments as $enrollment) {
            if ($enrollment->offering === null || ! in_array($enrollment->classification, [EnrollmentClassification::Preparatory, EnrollmentClassification::Core], true)) {
                continue;
            }
            $courseId = (int) $enrollment->offering->course_id;
            if ($enrollment->earned === false) {
                MustRetakeObligation::query()->firstOrCreate(
                    ['student_id' => $enrollment->student_id, 'course_id' => $courseId, 'state' => 'Active'],
                    ['origin_enrollment_id' => $enrollment->id, 'category' => $enrollment->classification->value, 'credit_hours' => $enrollment->credit_hours],
                );
            } elseif ($enrollment->earned === true) {
                MustRetakeObligation::query()->where('student_id', $enrollment->student_id)->where('course_id', $courseId)->where('state', 'Active')
                    ->update(['state' => 'Satisfied', 'closed_by_enrollment_id' => $enrollment->id, 'closed_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function releaseByExemption(int $studentId, int $courseId, int $exemptionDecisionId): void
    {
        MustRetakeObligation::query()->where('student_id', $studentId)->where('course_id', $courseId)->where('state', 'Active')
            ->update(['state' => 'ReleasedByExemption', 'closed_by_exemption_id' => $exemptionDecisionId, 'closed_at' => now(), 'updated_at' => now()]);
    }

    private function summary(MustRetakeObligation $item): MustRetakeObligationSummary
    {
        return new MustRetakeObligationSummary($item->id, $item->student_id, $item->course_id, $item->origin_enrollment_id, $item->category, $item->state);
    }
}
