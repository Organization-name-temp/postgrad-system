<?php

namespace Modules\Enrollment\Internal\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\TransferRequestTransition;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Models\WaiverRequestTransition;
use Modules\Enrollment\Internal\Support\EffectiveAttemptLookup;
use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectCreditRequestsAfterFailedGrade
{
    public function __construct(
        private readonly StudentGuard $students,
        private readonly EffectiveAttemptLookup $attempts,
        private readonly CourseCatalog $courses,
        private readonly DepartmentHeadAssignments $heads,
        private readonly Directory $directory,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(GradeOutcomesFinalized $event): void
    {
        $failed = Enrollment::query()
            ->with('offering')
            ->whereKey($event->enrollmentIds)
            ->where('earned', false)
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->course_status->burnsRetakeAllowance())
            ->groupBy('student_id');

        foreach ($failed as $studentId => $enrollments) {
            $courseIds = $enrollments->pluck('offering.course_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
            DB::transaction(function () use ($event, $studentId, $courseIds): void {
                $student = $this->students->lock((int) $studentId);
                if ($student === null) {
                    return;
                }
                $failedCourseIds = array_values(array_filter(
                    $courseIds,
                    fn (int $courseId): bool => $this->attempts->hasFailed($student->studentId, $courseId),
                ));
                if ($failedCourseIds === []) {
                    return;
                }

                // Approval and Grade finalization take the same Student lock. If approval
                // committed first, finalization must roll back: only Council may revoke its
                // decision. If finalization won first, approval observes the completed Attempt
                // and refuses the decision instead. Neither lock order can commit both facts.
                $approvedCollision = TransferDecision::query()->effective()
                    ->where('student_id', $student->studentId)
                    ->whereIn('target_course_id', $failedCourseIds)
                    ->exists()
                    || WaiverDecision::query()->effective()
                        ->where('student_id', $student->studentId)
                        ->whereIn('target_course_id', $failedCourseIds)
                        ->exists();
                if ($approvedCollision) {
                    throw new ConflictHttpException(__('credit_decisions.grade_finalization_conflict'));
                }

                $transfers = TransferRequest::query()->where('student_id', $student->studentId)
                    ->whereIn('target_course_id', $failedCourseIds)
                    ->whereIn('status', [
                        TransferRequestStatus::PendingAdvisor,
                        TransferRequestStatus::PendingDepartmentHead,
                        TransferRequestStatus::PendingCouncil,
                    ])->lockForUpdate()->get();
                foreach ($transfers as $request) {
                    $from = $request->status;
                    $request->update(['status' => TransferRequestStatus::Rejected]);
                    $transition = TransferRequestTransition::query()->create([
                        'transfer_request_id' => $request->id,
                        'from_status' => $from,
                        'to_status' => TransferRequestStatus::Rejected,
                        'action' => 'system_rejected_failed_attempt',
                        'actor_user_id' => null,
                        'revision' => $request->revision,
                        'evidence_snapshot' => $this->transferEvidence($request),
                        'review_snapshot' => ['normalized_grade' => $request->normalized_grade],
                        'system_cause_type' => 'grade_sheet',
                        'system_cause_id' => $event->gradeSheetId,
                        'reason' => __('credit_decisions.failed_grade_rejection'),
                        'created_at' => now(),
                    ]);
                    $this->notify($student, 'transfer', $request, $from->value, $transition->id, NotificationType::TransferRequestDecided);
                    $this->audit($student->studentId, 'transfer', $request->id, $event->gradeSheetId);
                }

                $waivers = WaiverRequest::query()->where('student_id', $student->studentId)
                    ->whereIn('target_course_id', $failedCourseIds)
                    ->whereIn('status', [
                        WaiverRequestStatus::PendingAdvisor,
                        WaiverRequestStatus::PendingDepartmentHead,
                        WaiverRequestStatus::PendingCouncil,
                    ])->lockForUpdate()->get();
                foreach ($waivers as $request) {
                    $from = $request->status;
                    $request->update(['status' => WaiverRequestStatus::Rejected]);
                    $transition = WaiverRequestTransition::query()->create([
                        'waiver_request_id' => $request->id,
                        'from_status' => $from,
                        'to_status' => WaiverRequestStatus::Rejected,
                        'action' => 'system_rejected_failed_attempt',
                        'actor_user_id' => null,
                        'revision' => $request->revision,
                        'evidence_snapshot' => $request->evidence,
                        'verification_snapshot' => ['coverage_percentage' => $request->coverage_percentage],
                        'system_cause_type' => 'grade_sheet',
                        'system_cause_id' => $event->gradeSheetId,
                        'reason' => __('credit_decisions.failed_grade_rejection'),
                        'created_at' => now(),
                    ]);
                    $this->notify($student, 'waiver', $request, $from->value, $transition->id, NotificationType::WaiverDecided);
                    $this->audit($student->studentId, 'waiver', $request->id, $event->gradeSheetId);
                }
            });
        }
    }

    /** @param TransferRequest|WaiverRequest $request */
    private function notify(StudentSummary $student, string $type, $request, string $from, int $transitionId, NotificationType $notificationType): void
    {
        $payload = [
            $type.'_request_id' => $request->id,
            'target_course_code' => $request->target_course_code,
            'outcome' => 'Rejected',
            'reason' => __('credit_decisions.failed_grade_rejection'),
        ];
        $key = $type.'-transition:'.$transitionId;
        $this->notifier->notifyOnce($student->userId, $notificationType, $key.':student', $payload);
        foreach ($this->reviewers($student->advisorId, $request->target_course_id, $from) as $reviewerId) {
            $this->notifier->notifyOnce($reviewerId, $notificationType, $key.':reviewer:'.$reviewerId, [...$payload, 'audience' => 'reviewer']);
        }
    }

    /** @return list<int> */
    private function reviewers(int $advisorId, int $courseId, string $status): array
    {
        if ($status === 'PendingAdvisor') {
            return [$advisorId];
        }
        if ($status === 'PendingDepartmentHead') {
            $course = $this->courses->find($courseId);

            return $course === null ? [] : $this->heads->headsForDepartment($course->departmentId);
        }

        return array_map(fn ($user): int => $user->id, $this->directory->usersByRole(Role::Council));
    }

    /** @return array<string, mixed> */
    private function transferEvidence(TransferRequest $request): array
    {
        return [
            'external_institution' => $request->external_institution,
            'external_course_code' => $request->external_course_code,
            'external_course_name' => $request->external_course_name,
            'source_credit_hours' => $request->source_credit_hours,
            'external_grade' => $request->external_grade,
            'document_ids' => $request->document_ids,
            'student_note' => $request->student_note,
        ];
    }

    private function audit(int $studentId, string $type, int $requestId, int $gradeSheetId): void
    {
        $this->audit->record(
            action: $type.'.request.system_rejected_failed_attempt',
            metadata: ['student_id' => $studentId, 'grade_sheet_id' => $gradeSheetId],
            subjectType: $type.'_request',
            subjectId: (string) $requestId,
        );
    }
}
