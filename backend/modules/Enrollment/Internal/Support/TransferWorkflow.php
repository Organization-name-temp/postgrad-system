<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\TransferRequestTransition;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransferWorkflow
{
    /** @var list<string> */
    private const ELIGIBLE_GRADES = ['A+', 'A', 'B+', 'B'];

    public function __construct(
        private readonly CurriculumCatalog $curricula,
        private readonly CourseCatalog $courses,
        private readonly StudentGuard $studentGuard,
        private readonly DepartmentHeadAssignments $heads,
        private readonly Directory $directory,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $evidence */
    public function create(StudentSummary $student, int $targetCourseId, array $evidence, ?string $studentNote): TransferRequest
    {
        return DB::transaction(function () use ($student, $targetCourseId, $evidence, $studentNote): TransferRequest {
            $guarded = $this->guardedStudent($student->studentId);
            abort_unless($guarded->userId === $student->userId, 403);
            $this->assertActive($guarded);
            $entry = $this->targetEntry($guarded, $targetCourseId);

            $request = TransferRequest::query()->create([
                'student_id' => $guarded->studentId,
                'curriculum_version_id' => $guarded->curriculumVersionId,
                'target_course_id' => $entry->courseId,
                'target_course_code' => $entry->courseCode,
                'target_category' => $entry->category,
                'target_credit_hours' => $entry->totalCh,
                ...$evidence,
                'student_note' => $studentNote,
                'status' => TransferRequestStatus::Draft,
                'revision' => 1,
            ]);
            $this->recordTransition($request, null, TransferRequestStatus::Draft, 'draft_created', $guarded->userId, null, null);
            $this->audit($request, 'created', $guarded->userId);

            return $this->fresh($request);
        });
    }

    /** @param array<string, mixed> $changes */
    public function revise(TransferRequest $request, int $actorUserId, int $expectedRevision, array $changes): TransferRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $changes): TransferRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }

            $dirty = false;
            foreach ([
                'external_institution', 'external_course_code', 'external_course_name',
                'source_credit_hours', 'external_grade', 'document_ids', 'student_note',
            ] as $field) {
                if (array_key_exists($field, $changes) && $locked->{$field} != $changes[$field]) {
                    $locked->{$field} = $changes[$field];
                    $dirty = true;
                }
            }
            if ($dirty) {
                $locked->revision++;
                $locked->normalized_grade = null;
                $locked->verified_by_user_id = null;
                $locked->verified_at = null;
                $locked->save();
                $this->audit($locked, 'revised', $actorUserId);
            }

            return $this->fresh($locked);
        });
    }

    public function abandon(TransferRequest $request, int $actorUserId, int $expectedRevision): TransferRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision): TransferRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== TransferRequestStatus::Draft) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }
            $locked->update(['status' => TransferRequestStatus::Abandoned]);
            $this->recordTransition($locked, TransferRequestStatus::Draft, TransferRequestStatus::Abandoned, 'abandoned', $actorUserId, null, null);
            $this->audit($locked, 'abandoned', $actorUserId);

            return $this->fresh($locked);
        });
    }

    public function submit(TransferRequest $request, int $actorUserId, int $expectedRevision, string $actionId): TransferRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId): TransferRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, 'submitted')) {
                return $replayed;
            }
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, 'submitted')) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }
            if ($locked->status === TransferRequestStatus::Returned
                && (int) $locked->transitions()->whereIn('action', ['returned_by_advisor', 'returned_by_department_head', 'returned_by_council'])->latest('id')->value('revision') >= $locked->revision) {
                throw new ConflictHttpException(__('credit_decisions.transfer.revision_required'));
            }
            if (now()->greaterThan($student->admittedAt->copy()->addMonthNoOverflow())) {
                throw new ConflictHttpException(__('credit_decisions.transfer.deadline_passed'));
            }
            $this->targetEntry($student, $locked->target_course_id);
            $this->assertNoEffectiveDecision($locked);
            $from = $locked->status;
            $locked->update([
                'status' => TransferRequestStatus::PendingAdvisor,
                'submitted_at' => now(),
                'normalized_grade' => null,
                'verified_by_user_id' => null,
                'verified_at' => null,
            ]);
            $transition = $this->recordTransition($locked, $from, TransferRequestStatus::PendingAdvisor, 'submitted', $actorUserId, null, $actionId);
            $this->notifier->notifyOnce($student->advisorId, NotificationType::TransferRequestSubmitted, 'transfer-transition:'.$transition->id, $this->payload($locked));
            $this->audit($locked, 'submitted', $actorUserId);

            return $this->fresh($locked);
        });
    }

    public function advisorDecision(TransferRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): TransferRequest
    {
        $transitionAction = match ($action) {
            'recommend' => 'recommended_by_advisor',
            'return' => 'returned_by_advisor',
            'reject' => 'rejected_by_advisor',
            default => throw new \InvalidArgumentException('Unsupported Advisor action.'),
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): TransferRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->advisorId === $actorUserId, 403);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== TransferRequestStatus::PendingAdvisor) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }
            $to = match ($action) {
                'recommend' => TransferRequestStatus::PendingDepartmentHead,
                'return' => TransferRequestStatus::Returned,
                'reject' => TransferRequestStatus::Rejected,
            };
            $locked->update(['status' => $to]);
            $transition = $this->recordTransition($locked, TransferRequestStatus::PendingAdvisor, $to, $transitionAction, $actorUserId, $reason, $actionId);
            if ($action === 'recommend') {
                foreach ($this->headsFor($locked) as $headId) {
                    $this->notifier->notifyOnce($headId, NotificationType::TransferRequestAdvanced, 'transfer-transition:'.$transition->id.':'.$headId, $this->payload($locked));
                }
            } else {
                $this->notifyStudentAndAdvisor($locked, $student, $transition, $action === 'return' ? NotificationType::TransferRequestReturned : NotificationType::TransferRequestDecided, $reason);
            }
            $this->audit($locked, $action === 'recommend' ? 'recommended' : $action.'ed', $actorUserId, $reason);

            return $this->fresh($locked);
        });
    }

    public function departmentHeadDecision(TransferRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason, ?string $normalizedGrade): TransferRequest
    {
        $transitionAction = match ($action) {
            'verify' => 'verified_by_department_head',
            'return' => 'returned_by_department_head',
            'reject' => 'rejected_by_department_head',
            default => throw new \InvalidArgumentException('Unsupported Department Head action.'),
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $normalizedGrade, $transitionAction): TransferRequest {
            $student = $this->guardedStudent($request->student_id);
            $this->authorizeDepartmentHeadScope($request, $actorUserId);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== TransferRequestStatus::PendingDepartmentHead) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }
            if ($action === 'verify' && ! in_array($normalizedGrade, self::ELIGIBLE_GRADES, true)) {
                throw new ConflictHttpException(__('credit_decisions.transfer.normalized_grade_ineligible'));
            }
            $to = match ($action) {
                'verify' => TransferRequestStatus::PendingCouncil,
                'return' => TransferRequestStatus::Returned,
                'reject' => TransferRequestStatus::Rejected,
            };
            if ($action === 'verify') {
                $locked->normalized_grade = $normalizedGrade;
                $locked->verified_by_user_id = $actorUserId;
                $locked->verified_at = now();
            }
            $locked->status = $to;
            $locked->save();
            $transition = $this->recordTransition($locked, TransferRequestStatus::PendingDepartmentHead, $to, $transitionAction, $actorUserId, $reason, $actionId, $action === 'verify' ? [
                'equivalency_verified' => true,
                'normalized_grade' => $normalizedGrade,
                'verified_by_user_id' => $actorUserId,
                'verified_at' => $locked->verified_at->toIso8601String(),
            ] : null);
            if ($action === 'verify') {
                $this->notifyCouncil($locked, $transition);
            } else {
                $this->notifyStudentAndAdvisor($locked, $student, $transition, $action === 'return' ? NotificationType::TransferRequestReturned : NotificationType::TransferRequestDecided, $reason);
            }
            $this->audit($locked, $action === 'verify' ? 'verified' : $action.'ed', $actorUserId, $reason);

            return $this->fresh($locked);
        });
    }

    public function councilDecision(TransferRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): TransferRequest
    {
        $transitionAction = match ($action) {
            'approve' => 'approved_by_council',
            'return' => 'returned_by_council',
            'reject' => 'rejected_by_council',
            default => throw new \InvalidArgumentException('Unsupported Council action.'),
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): TransferRequest {
            // Identity-first row lock serializes the lifetime cap for this Student before
            // any Enrollment-owned request or decision lock is taken.
            $student = $this->guardedStudent($request->student_id);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = TransferRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($replayed = $this->replayed($locked, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== TransferRequestStatus::PendingCouncil) {
                throw new ConflictHttpException(__('credit_decisions.transfer.invalid_state'));
            }

            $to = match ($action) {
                'approve' => TransferRequestStatus::Approved,
                'return' => TransferRequestStatus::Returned,
                'reject' => TransferRequestStatus::Rejected,
            };
            $decision = null;
            if ($action === 'approve') {
                if (! in_array($locked->normalized_grade, self::ELIGIBLE_GRADES, true)
                    || $locked->verified_by_user_id === null || $locked->verified_at === null) {
                    throw new ConflictHttpException(__('credit_decisions.transfer.verification_required'));
                }
                $entry = $this->targetEntry($student, $locked->target_course_id);
                if ($entry->totalCh !== $locked->target_credit_hours) {
                    throw new ConflictHttpException(__('credit_decisions.transfer.target_changed'));
                }
                $this->assertNoEffectiveDecision($locked);
                TransferDecision::query()->where('student_id', $student->studentId)->lockForUpdate()->get();
                $recognized = (int) TransferDecision::query()->where('student_id', $student->studentId)->sum('target_credit_hours');
                if ($recognized + $entry->totalCh > (int) config('enrollment.max_transfer_credit_hours', 9)) {
                    throw new ConflictHttpException(__('credit_decisions.transfer.cap_exceeded'));
                }
                $decision = TransferDecision::query()->create([
                    ...$this->evidence($locked),
                    'student_id' => $locked->student_id,
                    'curriculum_version_id' => $locked->curriculum_version_id,
                    'target_course_id' => $locked->target_course_id,
                    'target_course_code' => $locked->target_course_code,
                    'target_category' => $locked->target_category,
                    'target_credit_hours' => $entry->totalCh,
                    'normalized_grade' => $locked->normalized_grade,
                    'verified_by_user_id' => $locked->verified_by_user_id,
                    'verified_at' => $locked->verified_at,
                    'approved_by_user_id' => $actorUserId,
                    'approved_at' => now(),
                    'transfer_request_id' => $locked->getKey(),
                ]);
            }
            $locked->update(['status' => $to]);
            $transition = $this->recordTransition($locked, TransferRequestStatus::PendingCouncil, $to, $transitionAction, $actorUserId, $reason, $actionId, [
                'normalized_grade' => $locked->normalized_grade,
                'decision_id' => $decision?->getKey(),
            ]);
            $this->notifyStudentAndAdvisor($locked, $student, $transition, $action === 'return' ? NotificationType::TransferRequestReturned : NotificationType::TransferRequestDecided, $reason);
            $this->audit($locked, $action === 'approve' ? 'approved' : $action.'ed', $actorUserId, $reason, $decision?->getKey());

            return $this->fresh($locked);
        });
    }

    private function targetEntry(StudentSummary $student, int $targetCourseId): BoundCurriculumEntrySummary
    {
        $entry = collect($this->curricula->entriesForVersion($student->curriculumVersionId))
            ->first(fn (BoundCurriculumEntrySummary $candidate): bool => $candidate->courseId === $targetCourseId);
        abort_if($entry === null, 422, __('credit_decisions.transfer.invalid_target'));

        return $entry;
    }

    private function guardedStudent(int $studentId): StudentSummary
    {
        $student = $this->studentGuard->lock($studentId);
        abort_if($student === null, 404);

        return $student;
    }

    private function assertActive(StudentSummary $student): void
    {
        if ($student->lifecycleStatus !== StudentLifecycleStatus::Active) {
            throw new ConflictHttpException(__('credit_decisions.transfer.student_inactive'));
        }
    }

    private function assertRevision(TransferRequest $request, int $expectedRevision): void
    {
        if ($request->revision !== $expectedRevision) {
            throw new ConflictHttpException(__('credit_decisions.transfer.stale_request'));
        }
    }

    private function assertNoEffectiveDecision(TransferRequest $request): void
    {
        if (TransferDecision::query()->where('student_id', $request->student_id)->where('target_course_id', $request->target_course_id)->exists()
            || ExemptionDecision::query()->where('student_id', $request->student_id)->where('target_course_id', $request->target_course_id)->exists()) {
            throw new ConflictHttpException(__('credit_decisions.transfer.effective_decision_exists'));
        }
    }

    /** @return list<int> */
    private function headsFor(TransferRequest $request): array
    {
        $course = $this->courses->find($request->target_course_id);
        abort_if($course === null, 409, __('credit_decisions.transfer.target_unavailable'));

        return $this->heads->headsForDepartment($course->departmentId);
    }

    private function authorizeDepartmentHeadScope(TransferRequest $request, int $actorUserId): void
    {
        $course = $this->courses->find($request->target_course_id);
        abort_unless($course !== null && in_array($actorUserId, $this->heads->headsForDepartment($course->departmentId), true), 403);
    }

    private function notifyCouncil(TransferRequest $request, TransferRequestTransition $transition): void
    {
        foreach ($this->directory->usersByRole(Role::Council) as $council) {
            $councilId = $council->id;
            $this->notifier->notifyOnce($councilId, NotificationType::TransferRequestAdvanced, 'transfer-transition:'.$transition->id.':'.$councilId, $this->payload($request));
        }
    }

    private function notifyStudentAndAdvisor(TransferRequest $request, StudentSummary $student, TransferRequestTransition $transition, NotificationType $type, ?string $reason): void
    {
        $payload = [...$this->payload($request), 'outcome' => $request->status->value, 'reason' => $reason];
        $this->notifier->notifyOnce($student->userId, $type, 'transfer-transition:'.$transition->id.':student', $payload);
        $this->notifier->notifyOnce($student->advisorId, $type, 'transfer-transition:'.$transition->id.':advisor', [...$payload, 'audience' => 'advisor']);
    }

    /** @return array<string, mixed> */
    private function evidence(TransferRequest $request): array
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

    /** @param array<string, mixed>|null $review */
    private function recordTransition(TransferRequest $request, ?TransferRequestStatus $from, TransferRequestStatus $to, string $action, int $actorUserId, ?string $reason, ?string $actionId, ?array $review = null): TransferRequestTransition
    {
        return TransferRequestTransition::query()->create([
            'transfer_request_id' => $request->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'revision' => $request->revision,
            'evidence_snapshot' => $this->evidence($request),
            'review_snapshot' => $review,
            'action_id' => $actionId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function replayed(TransferRequest $request, int $actorUserId, string $actionId, string $action): ?TransferRequest
    {
        $transition = TransferRequestTransition::query()->where('action_id', $actionId)->first();
        if ($transition === null) {
            return null;
        }
        if ($transition->transfer_request_id !== $request->getKey() || $transition->actor_user_id !== $actorUserId || $transition->action !== $action) {
            throw new ConflictHttpException(__('credit_decisions.transfer.stale_request'));
        }

        return $this->fresh($request);
    }

    private function fresh(TransferRequest $request): TransferRequest
    {
        return $request->fresh(['transitions', 'decision']);
    }

    /** @return array<string, mixed> */
    private function payload(TransferRequest $request): array
    {
        return [
            'transfer_request_id' => $request->getKey(),
            'course_code' => $request->target_course_code,
            'revision' => $request->revision,
        ];
    }

    private function audit(TransferRequest $request, string $action, int $actorUserId, ?string $reason = null, ?int $decisionId = null): void
    {
        $this->audit->record(
            action: 'transfer.request.'.$action,
            actorUserId: $actorUserId,
            metadata: array_filter([
                'student_id' => $request->student_id,
                'target_course_id' => $request->target_course_id,
                'revision' => $request->revision,
                'reason' => $reason,
                'decision_id' => $decisionId,
            ], static fn (mixed $value): bool => $value !== null),
            subjectType: 'transfer_request',
            subjectId: (string) $request->getKey(),
        );
    }
}
