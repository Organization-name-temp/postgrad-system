<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Enums\RemediationState;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RemediationObligation;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Enrollment\Public\RemediationObligationSummary;
use Modules\Enrollment\Public\RemediationTransitionSummary;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class RemediationReconciler implements RemediationObligations
{
    public function __construct(
        private AcademicCalendar $calendar,
        private CourseCatalog $courses,
        private StudentDirectory $students,
        private StudentGuard $studentGuard,
        private DepartmentHeadAssignments $heads,
        private Notifier $notifier,
    ) {}

    public function createFromRevocation(int $revocationId, bool $studentGuardHeld = false): RemediationObligationSummary
    {
        $revocation = CreditDecisionRevocation::query()->findOrFail($revocationId);
        $item = RemediationObligation::query()->firstOrCreate(
            ['revocation_id' => $revocationId],
            ['student_id' => $revocation->student_id, 'course_id' => $revocation->target_course_id, 'state' => RemediationState::AwaitingOffering],
        );
        if ($item->wasRecentlyCreated) {
            $this->notifyChange($item, -((int) $item->id), 'created');
        }
        if ($studentGuardHeld) {
            $this->reconcileStudentState($item->student_id);
        } else {
            $this->reconcileStudent($item->student_id);
        }

        return $this->summary($item->fresh());
    }

    public function reconcileStudent(int $studentId): void
    {
        DB::transaction(function () use ($studentId): void {
            $student = $this->studentGuard->lock($studentId);
            if ($student === null || $student->lifecycleStatus !== StudentLifecycleStatus::Active) {
                return;
            }
            $this->reconcileStudentState($studentId);
        });
    }

    private function reconcileStudentState(int $studentId): void
    {
        foreach (RemediationObligation::query()->where('student_id', $studentId)->whereIn('state', [RemediationState::AwaitingOffering, RemediationState::Active])->get() as $item) {
            if (Enrollment::query()->where('student_id', $studentId)->where('earned', true)->whereHas('offering', fn ($query) => $query->where('course_id', $item->course_id))->exists()) {
                $from = $item->state->value;
                $item->update(['state' => RemediationState::Satisfied, 'closed_at' => now()]);
                $transitionId = \DB::table('remediation_obligation_transitions')->insertGetId(['remediation_obligation_id' => $item->id, 'from_state' => $from, 'to_state' => RemediationState::Satisfied->value, 'evidence' => json_encode(['course_id' => $item->course_id]), 'created_at' => now()]);
                $this->notifyChange($item, $transitionId, 'satisfied');

                continue;
            }
            $usedOpportunity = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('course_offering_id', $item->opportunity_offering_id)
                ->where('course_status', '!=', CourseStatus::Withdrawn)
                ->where('course_status', '!=', CourseStatus::Audit)
                ->exists();
            if ($usedOpportunity && $item->checkpoint_semester_id !== null) {
                $semester = $this->calendar->find((int) $item->checkpoint_semester_id);
                if ($semester !== null) {
                    $effective = $item->effective_deadline_at === null || $semester->gradeSubmissionDeadlineAt->greaterThan($item->effective_deadline_at)
                        ? $semester->gradeSubmissionDeadlineAt : $item->effective_deadline_at;
                    $item->fill(['official_deadline_at' => $semester->gradeSubmissionDeadlineAt, 'effective_deadline_at' => $effective]);
                    if ($item->isDirty()) {
                        $from = $item->state->value;
                        $item->save();
                        $transitionId = \DB::table('remediation_obligation_transitions')->insertGetId(['remediation_obligation_id' => $item->id, 'from_state' => $from, 'to_state' => $from, 'evidence' => json_encode(['opportunity_offering_id' => $item->opportunity_offering_id, 'official_deadline_at' => $semester->gradeSubmissionDeadlineAt->toIso8601String(), 'effective_deadline_at' => $effective->toIso8601String(), 'assignment_frozen' => true]), 'created_at' => now()]);
                        $this->notifyChange($item, $transitionId, 'deadline_changed');
                    }
                }

                continue;
            }
            $course = $this->courses->find((int) $item->course_id);
            $candidates = CourseOffering::query()->where('course_id', $item->course_id)->orderBy('semester_id')->orderBy('id')->get()
                ->map(fn (CourseOffering $offering): array => ['offering' => $offering, 'semester' => $this->calendar->find($offering->semester_id)])
                ->filter(fn (array $candidate): bool => $course?->isActive === true && $candidate['semester'] !== null && $candidate['semester']->lateAddEndsAt->isFuture())
                ->sortBy(fn (array $candidate) => $candidate['semester']->startsAt)->first();
            $from = $item->state->value;
            if ($candidates === null) {
                $changes = ['state' => RemediationState::AwaitingOffering, 'opportunity_offering_id' => null, 'checkpoint_semester_id' => null, 'official_deadline_at' => null, 'effective_deadline_at' => null];
            } else {
                $semester = $candidates['semester'];
                $effective = $item->effective_deadline_at === null || $semester->gradeSubmissionDeadlineAt->greaterThan($item->effective_deadline_at)
                    ? $semester->gradeSubmissionDeadlineAt : $item->effective_deadline_at;
                $changes = ['state' => RemediationState::Active, 'opportunity_offering_id' => $candidates['offering']->id, 'checkpoint_semester_id' => $semester->id, 'official_deadline_at' => $semester->gradeSubmissionDeadlineAt, 'effective_deadline_at' => $effective];
            }
            $item->fill($changes);
            if ($item->isDirty()) {
                $item->save();
                $transitionId = \DB::table('remediation_obligation_transitions')->insertGetId(['remediation_obligation_id' => $item->id, 'from_state' => $from, 'to_state' => $changes['state']->value, 'evidence' => json_encode($changes), 'created_at' => now()]);
                $this->notifyChange($item, $transitionId, 'opportunity_changed');
            }
        }
    }

    public function reconcileCourse(int $courseId): void
    {
        RemediationObligation::query()
            ->where('course_id', $courseId)
            ->whereIn('state', [RemediationState::AwaitingOffering, RemediationState::Active])
            ->distinct()
            ->pluck('student_id')
            ->each(fn (int $studentId) => $this->reconcileStudent($studentId));
    }

    public function reconcileSemester(int $semesterId): void
    {
        $courseIds = CourseOffering::query()->where('semester_id', $semesterId)->pluck('course_id');
        RemediationObligation::query()
            ->whereIn('state', [RemediationState::AwaitingOffering, RemediationState::Active])
            ->where(function ($query) use ($semesterId, $courseIds): void {
                $query->where('checkpoint_semester_id', $semesterId)->orWhereIn('course_id', $courseIds);
            })
            ->distinct()
            ->pluck('student_id')
            ->each(fn (int $studentId) => $this->reconcileStudent($studentId));
    }

    public function forStudent(int $studentId): array
    {
        return RemediationObligation::query()->where('student_id', $studentId)->orderBy('id')->get()->map(fn (RemediationObligation $item): RemediationObligationSummary => $this->summary($item))->all();
    }

    public function releaseByExemption(int $studentId, int $courseId, int $decisionId): void
    {
        foreach (RemediationObligation::query()->where('student_id', $studentId)->where('course_id', $courseId)->whereIn('state', [RemediationState::AwaitingOffering, RemediationState::Active])->lockForUpdate()->get() as $item) {
            $from = $item->state->value;
            $item->update(['state' => RemediationState::ReleasedByExemption, 'released_by_exemption_id' => $decisionId, 'closed_at' => now()]);
            $transitionId = \DB::table('remediation_obligation_transitions')->insertGetId(['remediation_obligation_id' => $item->id, 'from_state' => $from, 'to_state' => RemediationState::ReleasedByExemption->value, 'evidence' => json_encode(['exemption_decision_id' => $decisionId]), 'created_at' => now()]);
            $this->notifyChange($item, $transitionId, 'released_by_exemption');
        }
        foreach (RegistrationRequest::query()->where('student_id', $studentId)->whereIn('status', [RegistrationStatus::Pending, RegistrationStatus::AwaitingCapacityOverride])->whereHas('lines.offering', fn ($query) => $query->where('course_id', $courseId))->lockForUpdate()->get() as $request) {
            $from = $request->status->value;
            $request->update(['status' => RegistrationStatus::Rejected, 'advisor_note' => __('credit_decisions.registration.remediation_released'), 'decided_at' => now()]);
            \DB::table('registration_request_transitions')->insertOrIgnore(['registration_request_id' => $request->id, 'from_status' => $from, 'to_status' => RegistrationStatus::Rejected->value, 'action' => 'system_remediation_released', 'system_cause_type' => 'exemption_decision', 'system_cause_id' => $decisionId, 'created_at' => now()]);
            $student = $this->students->findByStudentId($studentId);
            if ($student !== null) {
                $course = $this->courses->find($courseId);
                $reviewers = $from === RegistrationStatus::AwaitingCapacityOverride->value && $course !== null
                    ? $this->heads->headsForDepartment($course->departmentId)
                    : [];
                foreach (array_unique([$student->userId, $student->advisorId, ...$reviewers]) as $recipient) {
                    $this->notifier->notifyOnce($recipient, NotificationType::AcademicRequestClosed, "remediation-release:{$decisionId}:registration:{$request->id}:{$recipient}", ['request_type' => 'registration', 'request_id' => $request->id, 'exemption_decision_id' => $decisionId]);
                }
            }
        }
    }

    public function markEvaluated(int $obligationId, string $expectedEffectiveDeadline): void
    {
        $item = RemediationObligation::query()->lockForUpdate()->findOrFail($obligationId);
        if ($item->state === RemediationState::Evaluated && $item->effective_deadline_at?->toIso8601String() === $expectedEffectiveDeadline) {
            return;
        }
        if ($item->state !== RemediationState::Active || $item->effective_deadline_at?->toIso8601String() !== $expectedEffectiveDeadline) {
            throw new ConflictHttpException(__('credit_decisions.registration.remediation_checkpoint_changed'));
        }
        $item->update(['state' => RemediationState::Evaluated, 'closed_at' => now()]);
        $transitionId = \DB::table('remediation_obligation_transitions')->insertGetId(['remediation_obligation_id' => $item->id, 'from_state' => RemediationState::Active->value, 'to_state' => RemediationState::Evaluated->value, 'evidence' => json_encode(['effective_deadline_at' => $expectedEffectiveDeadline]), 'created_at' => now()]);
        $this->notifyChange($item, $transitionId, 'evaluated');
    }

    private function summary(RemediationObligation $item): RemediationObligationSummary
    {
        $transitions = DB::table('remediation_obligation_transitions')
            ->where('remediation_obligation_id', $item->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $transition): RemediationTransitionSummary => new RemediationTransitionSummary(
                (int) $transition->id,
                $transition->from_state,
                $transition->to_state,
                json_decode($transition->evidence, true, flags: JSON_THROW_ON_ERROR),
                (string) $transition->created_at,
            ))
            ->all();

        return new RemediationObligationSummary($item->id, $item->student_id, $item->course_id, $item->revocation_id, $item->state->value, $item->opportunity_offering_id, $item->checkpoint_semester_id, $item->effective_deadline_at?->toIso8601String(), $transitions);
    }

    private function notifyChange(RemediationObligation $item, int $transitionId, string $change): void
    {
        $student = $this->students->findByStudentId((int) $item->student_id);
        $course = $this->courses->find((int) $item->course_id);
        if ($student === null) {
            return;
        }
        $recipients = [$student->userId, $student->advisorId];
        if ($course !== null) {
            $recipients = [...$recipients, ...$this->heads->headsForDepartment($course->departmentId)];
        }
        $payload = ['obligation_id' => $item->id, 'course_id' => $item->course_id, 'state' => $item->state->value, 'change' => $change, 'effective_deadline' => $item->effective_deadline_at?->toIso8601String()];
        foreach (array_unique($recipients) as $recipient) {
            $this->notifier->notifyOnce($recipient, NotificationType::RemediationChanged, "remediation-transition:{$transitionId}:{$recipient}", $payload);
        }
    }
}
