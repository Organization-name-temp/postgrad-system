<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\RemediationObligation;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Enrollment\Public\RemediationObligationSummary;

final readonly class RemediationReconciler implements RemediationObligations
{
    public function __construct(private AcademicCalendar $calendar) {}

    public function createFromRevocation(int $revocationId): RemediationObligationSummary
    {
        $revocation = CreditDecisionRevocation::query()->findOrFail($revocationId);
        $item = RemediationObligation::query()->firstOrCreate(
            ['revocation_id' => $revocationId],
            ['student_id' => $revocation->student_id, 'course_id' => $revocation->target_course_id, 'state' => 'AwaitingOffering'],
        );
        $this->reconcileStudent($item->student_id);

        return $this->summary($item->fresh());
    }

    public function reconcileStudent(int $studentId): void
    {
        foreach (RemediationObligation::query()->where('student_id', $studentId)->whereIn('state', ['AwaitingOffering', 'Active'])->get() as $item) {
            $candidates = CourseOffering::query()->where('course_id', $item->course_id)->orderBy('semester_id')->orderBy('id')->get()
                ->map(fn (CourseOffering $offering): array => ['offering' => $offering, 'semester' => $this->calendar->find($offering->semester_id)])
                ->filter(fn (array $candidate): bool => $candidate['semester'] !== null && $candidate['semester']->lateAddEndsAt->isFuture())
                ->sortBy(fn (array $candidate) => $candidate['semester']->startsAt)->first();
            $from = $item->state;
            if ($candidates === null) {
                $changes = ['state' => 'AwaitingOffering', 'opportunity_offering_id' => null, 'checkpoint_semester_id' => null, 'official_deadline_at' => null];
            } else {
                $semester = $candidates['semester'];
                $effective = $item->effective_deadline_at === null || $semester->gradeSubmissionDeadlineAt->greaterThan($item->effective_deadline_at)
                    ? $semester->gradeSubmissionDeadlineAt : $item->effective_deadline_at;
                $changes = ['state' => 'Active', 'opportunity_offering_id' => $candidates['offering']->id, 'checkpoint_semester_id' => $semester->id, 'official_deadline_at' => $semester->gradeSubmissionDeadlineAt, 'effective_deadline_at' => $effective];
            }
            $item->fill($changes);
            if ($item->isDirty()) {
                $item->save();
                \DB::table('remediation_obligation_transitions')->insert(['remediation_obligation_id' => $item->id, 'from_state' => $from, 'to_state' => $changes['state'], 'evidence' => json_encode($changes), 'created_at' => now()]);
            }
        }
    }

    public function forStudent(int $studentId): array
    {
        return RemediationObligation::query()->where('student_id', $studentId)->orderBy('id')->get()->map(fn (RemediationObligation $item): RemediationObligationSummary => $this->summary($item))->all();
    }

    public function releaseByExemption(int $studentId, int $courseId, int $decisionId): void
    {
        RemediationObligation::query()->where('student_id', $studentId)->where('course_id', $courseId)->whereIn('state', ['AwaitingOffering', 'Active'])
            ->update(['state' => 'ReleasedByExemption', 'released_by_exemption_id' => $decisionId, 'closed_at' => now(), 'updated_at' => now()]);
    }

    private function summary(RemediationObligation $item): RemediationObligationSummary
    {
        return new RemediationObligationSummary($item->id, $item->student_id, $item->course_id, $item->revocation_id, $item->state, $item->opportunity_offering_id, $item->checkpoint_semester_id, $item->effective_deadline_at?->toIso8601String());
    }
}
