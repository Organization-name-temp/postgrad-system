<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;
use Modules\Enrollment\Internal\Enums\ExemptionSource;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\ExemptionRequestTransition;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Public\Events\AcademicEvidenceChanged;
use Modules\Enrollment\Public\MustRetakeObligations;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ManualExemptionWorkflow
{
    public function __construct(
        private readonly CurriculumCatalog $curricula,
        private readonly CourseCatalog $courses,
        private readonly StudentGuard $studentGuard,
        private readonly DepartmentHeadAssignments $heads,
        private readonly ManualExemptionEligibility $eligibility,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
        private readonly MustRetakeObligations $mustRetakes,
        private readonly RemediationObligations $remediation,
    ) {}

    /** @param array<string, mixed> $evidence */
    public function create(StudentSummary $student, int $targetCourseId, array $evidence, ?string $studentNote): ExemptionRequest
    {
        return DB::transaction(function () use ($student, $targetCourseId, $evidence, $studentNote): ExemptionRequest {
            $guardedStudent = $this->guardedStudent($student->studentId);
            abort_unless($guardedStudent->userId === $student->userId, 403);
            $this->assertActive($guardedStudent);
            $entry = $this->targetEntry($guardedStudent, $targetCourseId);
            $request = ExemptionRequest::query()->create([
                'student_id' => $guardedStudent->studentId,
                'curriculum_version_id' => $guardedStudent->curriculumVersionId,
                'target_course_id' => $entry->courseId,
                'target_course_code' => $entry->courseCode,
                'preparatory_requirement_role' => $entry->preparatoryRequirementRole,
                'credit_hours' => $entry->totalCh,
                'evidence' => $evidence,
                'student_note' => $studentNote,
                'status' => ExemptionRequestStatus::Draft,
                'revision' => 1,
            ]);
            $this->recordTransition($request, null, ExemptionRequestStatus::Draft, 'draft_created', $guardedStudent->userId, null, null);
            $this->audit($request, 'created', $guardedStudent->userId);

            return $request->fresh(['transitions', 'decision']);
        });
    }

    /** @param array<string, mixed> $changes */
    public function revise(ExemptionRequest $request, int $actorUserId, int $expectedRevision, array $changes): ExemptionRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $changes): ExemptionRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = ExemptionRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.invalid_state'));
            }

            $dirty = false;
            foreach (['evidence', 'student_note'] as $field) {
                if (array_key_exists($field, $changes) && $locked->{$field} !== $changes[$field]) {
                    $locked->{$field} = $changes[$field];
                    $dirty = true;
                }
            }
            if ($dirty) {
                $locked->revision++;
                $locked->save();
                $this->audit($locked, 'revised', $actorUserId);
            }

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function abandon(ExemptionRequest $request, int $actorUserId, int $expectedRevision): ExemptionRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision): ExemptionRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = ExemptionRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== ExemptionRequestStatus::Draft) {
                throw new ConflictHttpException(__('credit_decisions.invalid_state'));
            }
            $from = $locked->status;
            $locked->update(['status' => ExemptionRequestStatus::Abandoned]);
            $this->recordTransition($locked, $from, ExemptionRequestStatus::Abandoned, 'abandoned', $actorUserId, null, null);
            $this->audit($locked, 'abandoned', $actorUserId);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function submit(ExemptionRequest $request, int $actorUserId, int $expectedRevision, string $actionId): ExemptionRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId): ExemptionRequest {
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, 'submitted')) {
                return $replayed;
            }
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = ExemptionRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, 'submitted')) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.invalid_state'));
            }
            if ($locked->status === ExemptionRequestStatus::Returned
                && (int) $locked->transitions()->whereIn('action', ['returned_by_advisor', 'returned_by_department_head'])->latest('id')->value('revision') >= $locked->revision) {
                throw new ConflictHttpException(__('credit_decisions.revision_required'));
            }
            $this->assertNoEffectiveDecision($locked);
            $from = $locked->status;
            $locked->update(['status' => ExemptionRequestStatus::PendingAdvisor, 'submitted_at' => now()]);
            $transition = $this->recordTransition($locked, $from, ExemptionRequestStatus::PendingAdvisor, 'submitted', $actorUserId, null, $actionId);
            $this->notifier->notifyOnce($student->advisorId, NotificationType::ManualExemptionSubmitted, 'exemption-transition:'.$transition->id, $this->payload($locked));
            $this->audit($locked, 'submitted', $actorUserId);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function advisorDecision(ExemptionRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): ExemptionRequest
    {
        $transitionAction = match ($action) {
            'recommend' => 'recommended_by_advisor',
            'return' => 'returned_by_advisor',
            'reject' => 'rejected_by_advisor',
            default => throw new \InvalidArgumentException('Unsupported Advisor action.'),
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): ExemptionRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->advisorId === $actorUserId, 403);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = ExemptionRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== ExemptionRequestStatus::PendingAdvisor) {
                throw new ConflictHttpException(__('credit_decisions.invalid_state'));
            }
            $to = match ($action) {
                'recommend' => ExemptionRequestStatus::PendingDepartmentHead,
                'return' => ExemptionRequestStatus::Returned,
                'reject' => ExemptionRequestStatus::Rejected,
            };
            $locked->update(['status' => $to]);
            $transition = $this->recordTransition($locked, ExemptionRequestStatus::PendingAdvisor, $to, $transitionAction, $actorUserId, $reason, $actionId);
            if ($action === 'recommend') {
                foreach ($this->headsFor($locked) as $headId) {
                    $this->notifier->notifyOnce($headId, NotificationType::ManualExemptionRecommended, 'exemption-transition:'.$transition->id.':'.$headId, $this->payload($locked));
                }
            } else {
                $type = $action === 'return' ? NotificationType::ManualExemptionReturned : NotificationType::ManualExemptionDecided;
                $this->notifier->notifyOnce($student->userId, $type, 'exemption-transition:'.$transition->id, [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason]);
            }
            $this->audit($locked, $action === 'recommend' ? 'recommended' : $action.'ed', $actorUserId, $reason);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function departmentHeadDecision(ExemptionRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): ExemptionRequest
    {
        $transitionAction = match ($action) {
            'approve' => 'approved_by_department_head',
            'return' => 'returned_by_department_head',
            'reject' => 'rejected_by_department_head',
            default => throw new \InvalidArgumentException('Unsupported Department Head action.'),
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): ExemptionRequest {
            $student = $this->guardedStudent($request->student_id);
            $this->authorizeDepartmentHeadScope($request, $actorUserId);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = ExemptionRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== ExemptionRequestStatus::PendingDepartmentHead) {
                throw new ConflictHttpException(__('credit_decisions.invalid_state'));
            }
            // Reread through the guarded DTO: Identity's row lock must be acquired before
            // any Enrollment-owned row lock (ADR-0030/0040).
            $to = match ($action) {
                'approve' => ExemptionRequestStatus::Approved,
                'return' => ExemptionRequestStatus::Returned,
                'reject' => ExemptionRequestStatus::Rejected,
            };

            $decision = null;
            if ($action === 'approve') {
                $this->assertNoEffectiveDecision($locked);
                $reasonCodes = $this->eligibility->reasons($student, $locked->preparatory_requirement_role, $locked->evidence);
                if ($reasonCodes === []) {
                    throw new ConflictHttpException(__('credit_decisions.evidence_not_qualifying'));
                }
                $decision = ExemptionDecision::query()->create([
                    'student_id' => $locked->student_id,
                    'curriculum_version_id' => $locked->curriculum_version_id,
                    'target_course_id' => $locked->target_course_id,
                    'target_course_code' => $locked->target_course_code,
                    'preparatory_requirement_role' => $locked->preparatory_requirement_role,
                    'credit_hours' => $locked->credit_hours,
                    'source' => ExemptionSource::Manual,
                    'reason_codes' => $reasonCodes,
                    'source_evidence' => $locked->evidence,
                    'admission_application_id' => null,
                    'manual_request_id' => $locked->getKey(),
                    'approved_at' => now(),
                ]);
                $this->mustRetakes->releaseByExemption($student->studentId, $locked->target_course_id, (int) $decision->getKey());
                $this->remediation->releaseByExemption($student->studentId, $locked->target_course_id, (int) $decision->getKey());
                DB::afterCommit(fn () => Event::dispatch(new AcademicEvidenceChanged($student->studentId, 'exemption_decision', (int) $decision->getKey())));
            }

            $locked->update(['status' => $to]);
            $transition = $this->recordTransition($locked, ExemptionRequestStatus::PendingDepartmentHead, $to, $transitionAction, $actorUserId, $reason, $actionId);
            $type = $action === 'return' ? NotificationType::ManualExemptionReturned : NotificationType::ManualExemptionDecided;
            $payload = [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason];
            $this->notifier->notifyOnce($student->userId, $type, 'exemption-transition:'.$transition->id.':student', $payload);
            $this->notifier->notifyOnce($student->advisorId, $type, 'exemption-transition:'.$transition->id.':advisor', [...$payload, 'audience' => 'advisor']);
            $this->audit($locked, $action === 'approve' ? 'approved' : $action.'ed', $actorUserId, $reason, $decision?->getKey());

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    private function targetEntry(StudentSummary $student, int $targetCourseId): BoundCurriculumEntrySummary
    {
        $entry = collect($this->curricula->entriesForVersion($student->curriculumVersionId))
            ->first(fn (BoundCurriculumEntrySummary $candidate): bool => $candidate->courseId === $targetCourseId
                && $candidate->category === CourseCategory::Preparatory
                && $candidate->preparatoryRequirementRole !== null);
        abort_if($entry === null, 422, __('credit_decisions.invalid_target'));

        return $entry;
    }

    private function assertActive(StudentSummary $student): void
    {
        if ($student->lifecycleStatus !== StudentLifecycleStatus::Active) {
            throw new ConflictHttpException(__('credit_decisions.student_inactive'));
        }
    }

    private function guardedStudent(int $studentId): StudentSummary
    {
        $student = $this->studentGuard->lock($studentId);
        abort_if($student === null, 404);

        return $student;
    }

    private function assertRevision(ExemptionRequest $request, int $expectedRevision): void
    {
        if ($request->revision !== $expectedRevision) {
            throw new ConflictHttpException(__('credit_decisions.stale_request'));
        }
    }

    private function assertNoEffectiveDecision(ExemptionRequest $request): void
    {
        if (ExemptionDecision::query()->effective()->where('student_id', $request->student_id)->where('target_course_id', $request->target_course_id)->exists()
            || TransferDecision::query()->effective()->where('student_id', $request->student_id)->where('target_course_id', $request->target_course_id)->exists()
            || WaiverDecision::query()->effective()->where('student_id', $request->student_id)->where('target_course_id', $request->target_course_id)->exists()) {
            throw new ConflictHttpException(__('credit_decisions.effective_decision_exists'));
        }
    }

    /** @return list<int> */
    private function headsFor(ExemptionRequest $request): array
    {
        $course = $this->courses->find($request->target_course_id);
        abort_if($course === null, 409, __('credit_decisions.target_unavailable'));

        return $this->heads->headsForDepartment($course->departmentId);
    }

    private function authorizeDepartmentHeadScope(ExemptionRequest $request, int $actorUserId): void
    {
        $course = $this->courses->find($request->target_course_id);

        // Course IDs are opaque across the Enrollment/Catalog seam. A missing target cannot
        // establish Head scope, so authorization fails closed without disclosing domain state.
        abort_unless(
            $course !== null
                && in_array($actorUserId, $this->heads->headsForDepartment($course->departmentId), true),
            403,
        );
    }

    private function recordTransition(ExemptionRequest $request, ?ExemptionRequestStatus $from, ExemptionRequestStatus $to, string $action, int $actorUserId, ?string $reason, ?string $actionId): ExemptionRequestTransition
    {
        return ExemptionRequestTransition::query()->create([
            'exemption_request_id' => $request->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'revision' => $request->revision,
            'evidence_snapshot' => $request->evidence,
            'action_id' => $actionId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function replayed(ExemptionRequest $request, int $actorUserId, string $actionId, string $action): ?ExemptionRequest
    {
        $transition = ExemptionRequestTransition::query()->where('action_id', $actionId)->first();
        if ($transition === null) {
            return null;
        }
        if ($transition->exemption_request_id !== $request->getKey() || $transition->actor_user_id !== $actorUserId || $transition->action !== $action) {
            throw new ConflictHttpException(__('credit_decisions.stale_request'));
        }

        return $request->fresh(['transitions', 'decision']);
    }

    /** @return array<string, mixed> */
    private function payload(ExemptionRequest $request): array
    {
        return [
            'exemption_request_id' => $request->getKey(),
            'course_code' => $request->target_course_code,
            'preparatory_requirement_role' => $request->preparatory_requirement_role->value,
            'revision' => $request->revision,
        ];
    }

    private function audit(ExemptionRequest $request, string $action, int $actorUserId, ?string $reason = null, ?int $decisionId = null): void
    {
        $this->audit->record(
            action: 'exemption.request.'.$action,
            actorUserId: $actorUserId,
            metadata: array_filter([
                'student_id' => $request->student_id,
                'target_course_id' => $request->target_course_id,
                'revision' => $request->revision,
                'reason' => $reason,
                'decision_id' => $decisionId,
            ], static fn (mixed $value): bool => $value !== null),
            subjectType: 'exemption_request',
            subjectId: (string) $request->getKey(),
        );
    }
}
