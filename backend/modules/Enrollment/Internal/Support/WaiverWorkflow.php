<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Models\WaiverRequestTransition;
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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WaiverWorkflow
{
    private const MIN_COVERAGE = 80.0;

    /** @var list<string> */
    private const ELIGIBLE_GRADES = ['A+', 'A', 'B+', 'B', 'C+'];

    public function __construct(
        private readonly CurriculumCatalog $curricula,
        private readonly CourseCatalog $courses,
        private readonly StudentGuard $studentGuard,
        private readonly AdmissionDocuments $admissionDocuments,
        private readonly DepartmentHeadAssignments $heads,
        private readonly Directory $directory,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $priorResult */
    public function create(StudentSummary $student, int $targetCourseId, array $priorResult, ?string $studentNote): WaiverRequest
    {
        return DB::transaction(function () use ($student, $targetCourseId, $priorResult, $studentNote): WaiverRequest {
            $guarded = $this->guardedStudent($student->studentId);
            abort_unless($guarded->userId === $student->userId, 403);
            $this->assertActive($guarded);
            $target = $this->targetEntry($guarded, $targetCourseId);
            $prior = $this->resolvePriorResult($guarded, $priorResult);
            $this->assertNoEffectiveDecision($guarded->studentId, $target->courseId);
            $this->assertNoTargetAttempt($guarded->studentId, $target->courseId);

            $created = WaiverRequest::query()->create([
                'student_id' => $guarded->studentId,
                'curriculum_version_id' => $guarded->curriculumVersionId,
                'target_course_id' => $target->courseId,
                'target_course_code' => $target->courseCode,
                'credit_hours' => $target->totalCh,
                ...$prior,
                'evidence' => $priorResult,
                'student_note' => $studentNote,
                'status' => WaiverRequestStatus::Draft,
                'revision' => 1,
            ]);
            $this->transition($created, null, WaiverRequestStatus::Draft, 'draft_created', $guarded->userId, null, null);
            $this->audit($created, 'created', $guarded->userId);

            return $created->fresh(['transitions', 'decision']);
        });
    }

    public function submit(WaiverRequest $request, int $actorUserId, int $expectedRevision, string $actionId): WaiverRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, 'submitted')) {
                return $replayed;
            }
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            if ($locked->status === WaiverRequestStatus::Returned
                && (int) $locked->transitions()->where('action', 'like', 'returned_by_%')->latest('id')->value('revision') >= $locked->revision) {
                throw new ConflictHttpException(__('credit_decisions.waiver.revision_required'));
            }
            $from = $locked->status;
            $locked->update(['status' => WaiverRequestStatus::PendingAdvisor, 'submitted_at' => now()]);
            $transition = $this->transition($locked, $from, WaiverRequestStatus::PendingAdvisor, 'submitted', $actorUserId, null, $actionId);
            $this->notifier->notifyOnce($student->advisorId, NotificationType::WaiverSubmitted, 'waiver-transition:'.$transition->id, $this->payload($locked));
            $this->audit($locked, 'submitted', $actorUserId);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function abandon(WaiverRequest $request, int $actorUserId, int $expectedRevision): WaiverRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== WaiverRequestStatus::Draft) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            $locked->update(['status' => WaiverRequestStatus::Abandoned]);
            $this->transition($locked, WaiverRequestStatus::Draft, WaiverRequestStatus::Abandoned, 'abandoned', $actorUserId, null, null);
            $this->audit($locked, 'abandoned', $actorUserId);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    /** @param array<string, mixed> $changes */
    public function revise(WaiverRequest $request, int $actorUserId, int $expectedRevision, array $changes): WaiverRequest
    {
        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $changes): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->userId === $actorUserId, 403);
            $this->assertActive($student);
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if (! $locked->status->isEditable()) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            $dirty = false;
            if (array_key_exists('prior_result', $changes) && $locked->evidence !== $changes['prior_result']) {
                $locked->fill([...$this->resolvePriorResult($student, $changes['prior_result']), 'evidence' => $changes['prior_result']]);
                $dirty = true;
            }
            if (array_key_exists('student_note', $changes) && $locked->student_note !== $changes['student_note']) {
                $locked->student_note = $changes['student_note'];
                $dirty = true;
            }
            if ($dirty) {
                $locked->fill([
                    'revision' => $locked->revision + 1, 'coverage_percentage' => null,
                    'equivalency_note' => null, 'verified_by' => null, 'verified_at' => null,
                ])->save();
                $this->audit($locked, 'revised', $actorUserId);
            }

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function advisorDecision(WaiverRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): WaiverRequest
    {
        $transitionAction = match ($action) {
            'recommend' => 'recommended_by_advisor', 'return' => 'returned_by_advisor',
            'reject' => 'rejected_by_advisor', default => throw new \InvalidArgumentException,
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            abort_unless($student->advisorId === $actorUserId, 403);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== WaiverRequestStatus::PendingAdvisor) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            $to = match ($action) {
                'recommend' => WaiverRequestStatus::PendingDepartmentHead,
                'return' => WaiverRequestStatus::Returned,
                'reject' => WaiverRequestStatus::Rejected,
            };
            $locked->update(['status' => $to]);
            $transition = $this->transition($locked, WaiverRequestStatus::PendingAdvisor, $to, $transitionAction, $actorUserId, $reason, $actionId);
            if ($action === 'recommend') {
                foreach ($this->headsFor($locked) as $head) {
                    $this->notifier->notifyOnce($head, NotificationType::WaiverRecommended, 'waiver-transition:'.$transition->id.':'.$head, $this->payload($locked));
                }
            } else {
                $this->notifier->notifyOnce($student->userId, $action === 'return' ? NotificationType::WaiverReturned : NotificationType::WaiverDecided, 'waiver-transition:'.$transition->id, [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason]);
            }
            $this->audit($locked, $action === 'recommend' ? 'recommended' : $action.'ed', $actorUserId, $reason);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function departmentHeadDecision(WaiverRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason, ?float $coverage, ?string $equivalencyNote): WaiverRequest
    {
        $transitionAction = match ($action) {
            'verify' => 'verified_by_department_head', 'return' => 'returned_by_department_head',
            'reject' => 'rejected_by_department_head', default => throw new \InvalidArgumentException,
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $coverage, $equivalencyNote, $transitionAction): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            $this->authorizeHead($request, $actorUserId);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== WaiverRequestStatus::PendingDepartmentHead) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            $to = match ($action) {
                'verify' => WaiverRequestStatus::PendingCouncil,
                'return' => WaiverRequestStatus::Returned,
                'reject' => WaiverRequestStatus::Rejected,
            };
            $verification = null;
            if ($action === 'verify') {
                if ($coverage === null || $coverage < self::MIN_COVERAGE) {
                    throw new ConflictHttpException(__('credit_decisions.waiver.coverage_below_minimum'));
                }
                $verification = [
                    'coverage_percentage' => $coverage,
                    'equivalency_note' => $equivalencyNote,
                    'verified_by' => $actorUserId,
                    'verified_at' => now()->toIso8601String(),
                ];
                $locked->fill([
                    'coverage_percentage' => $coverage,
                    'equivalency_note' => $equivalencyNote,
                    'verified_by' => $actorUserId,
                    'verified_at' => $verification['verified_at'],
                ]);
            }
            $locked->status = $to;
            $locked->save();
            $transition = $this->transition($locked, WaiverRequestStatus::PendingDepartmentHead, $to, $transitionAction, $actorUserId, $reason, $actionId, $verification);
            if ($action === 'verify') {
                foreach ($this->directory->usersByRole(Role::Council) as $council) {
                    $this->notifier->notifyOnce($council->id, NotificationType::WaiverVerified, 'waiver-transition:'.$transition->id.':'.$council->id, $this->payload($locked));
                }
            } else {
                $this->notifier->notifyOnce($student->userId, $action === 'return' ? NotificationType::WaiverReturned : NotificationType::WaiverDecided, 'waiver-transition:'.$transition->id.':student', [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason]);
                $this->notifier->notifyOnce($student->advisorId, $action === 'return' ? NotificationType::WaiverReturned : NotificationType::WaiverDecided, 'waiver-transition:'.$transition->id.':advisor', [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason, 'audience' => 'advisor']);
            }
            $this->audit($locked, $action === 'verify' ? 'verified' : $action.'ed', $actorUserId, $reason);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    public function councilDecision(WaiverRequest $request, int $actorUserId, int $expectedRevision, string $actionId, string $action, ?string $reason): WaiverRequest
    {
        $transitionAction = match ($action) {
            'approve' => 'approved_by_council', 'return' => 'returned_by_council',
            'reject' => 'rejected_by_council', default => throw new \InvalidArgumentException,
        };

        return DB::transaction(function () use ($request, $actorUserId, $expectedRevision, $actionId, $action, $reason, $transitionAction): WaiverRequest {
            $student = $this->guardedStudent($request->student_id);
            $this->assertActive($student);
            if ($replayed = $this->replayed($request, $actorUserId, $actionId, $transitionAction)) {
                return $replayed;
            }
            $locked = WaiverRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRevision($locked, $expectedRevision);
            if ($locked->status !== WaiverRequestStatus::PendingCouncil) {
                throw new ConflictHttpException(__('credit_decisions.waiver.invalid_state'));
            }
            $to = match ($action) {
                'approve' => WaiverRequestStatus::Approved,
                'return' => WaiverRequestStatus::Returned,
                'reject' => WaiverRequestStatus::Rejected,
            };
            if ($action === 'approve') {
                $this->assertNoEffectiveDecision($locked->student_id, $locked->target_course_id);
                $this->assertNoTargetAttempt($locked->student_id, $locked->target_course_id);
                WaiverDecision::query()->create([
                    'waiver_request_id' => $locked->getKey(), 'student_id' => $locked->student_id,
                    'curriculum_version_id' => $locked->curriculum_version_id,
                    'target_course_id' => $locked->target_course_id, 'target_course_code' => $locked->target_course_code,
                    'credit_hours' => $locked->credit_hours, 'prior_course_id' => $locked->prior_course_id,
                    'prior_course_code' => $locked->prior_course_code, 'prior_course_level' => $locked->prior_course_level,
                    'prior_result_source' => $locked->prior_result_source, 'prior_letter_grade' => $locked->prior_letter_grade,
                    'coverage_percentage' => $locked->coverage_percentage, 'verified_by' => $locked->verified_by,
                    'verified_at' => $locked->verified_at, 'approved_by' => $actorUserId, 'approved_at' => now(),
                ]);
            }
            $locked->update(['status' => $to]);
            $transition = $this->transition($locked, WaiverRequestStatus::PendingCouncil, $to, $transitionAction, $actorUserId, $reason, $actionId, $this->verification($locked));
            $type = $action === 'return' ? NotificationType::WaiverReturned : NotificationType::WaiverDecided;
            $payload = [...$this->payload($locked), 'outcome' => $to->value, 'reason' => $reason];
            $this->notifier->notifyOnce($student->userId, $type, 'waiver-transition:'.$transition->id.':student', $payload);
            $this->notifier->notifyOnce($student->advisorId, $type, 'waiver-transition:'.$transition->id.':advisor', [...$payload, 'audience' => 'advisor']);
            foreach ($this->headsFor($locked) as $head) {
                $this->notifier->notifyOnce($head, $type, 'waiver-transition:'.$transition->id.':head:'.$head, [...$payload, 'audience' => 'department_head']);
            }
            $this->audit($locked, $action === 'approve' ? 'approved' : $action.'ed', $actorUserId, $reason);

            return $locked->fresh(['transitions', 'decision']);
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function resolvePriorResult(StudentSummary $student, array $input): array
    {
        if (($input['institution'] ?? null) !== 'E-JUST') {
            throw new UnprocessableEntityHttpException(__('credit_decisions.waiver.external_study'));
        }
        if (($input['source'] ?? null) === 'legacy_transcript') {
            $course = $this->courses->find((int) ($input['course_id'] ?? 0));
            $documents = $input['official_transcript_document_ids'] ?? [];
            $grade = (string) ($input['letter_grade'] ?? '');
            if ($course === null || ! in_array($grade, self::ELIGIBLE_GRADES, true)) {
                throw new UnprocessableEntityHttpException(__('credit_decisions.waiver.prior_result_not_qualifying'));
            }
            $ownedTranscripts = collect($this->admissionDocuments->forApplicant($student->userId))
                ->filter(fn ($document): bool => $document->type === 'transcript')
                ->keyBy(fn ($document): string => $document->id);
            if (! is_array($documents) || $documents === []
                || collect($documents)->contains(fn ($id): bool => ! is_string($id) || ! $ownedTranscripts->has($id))) {
                throw new UnprocessableEntityHttpException(__('credit_decisions.waiver.official_transcript_required'));
            }

            return [
                'prior_course_id' => $course->id, 'prior_course_code' => $course->code,
                'prior_course_level' => $course->level->value, 'prior_result_source' => 'legacy_transcript',
                'prior_enrollment_id' => null, 'prior_letter_grade' => $grade,
            ];
        }
        if (($input['source'] ?? null) !== 'prs') {
            throw new UnprocessableEntityHttpException(__('credit_decisions.waiver.invalid_prior_result'));
        }
        $enrollment = Enrollment::query()->with('offering')->find((int) ($input['enrollment_id'] ?? 0));
        abort_if($enrollment === null || $enrollment->student_id !== $student->studentId || $enrollment->earned !== true, 422, __('credit_decisions.waiver.prior_result_not_qualifying'));
        $course = $this->courses->find($enrollment->offering->course_id);
        abort_if($course === null, 422, __('credit_decisions.waiver.prior_result_not_qualifying'));

        return [
            'prior_course_id' => $course->id, 'prior_course_code' => $course->code,
            'prior_course_level' => $course->level->value, 'prior_result_source' => 'prs',
            'prior_enrollment_id' => $enrollment->getKey(), 'prior_letter_grade' => null,
        ];
    }

    private function targetEntry(StudentSummary $student, int $targetCourseId): BoundCurriculumEntrySummary
    {
        $entry = collect($this->curricula->entriesForVersion($student->curriculumVersionId))->first(
            fn (BoundCurriculumEntrySummary $candidate): bool => $candidate->courseId === $targetCourseId,
        );
        abort_if($entry === null, 422, __('credit_decisions.waiver.invalid_target'));

        return $entry;
    }

    private function assertNoEffectiveDecision(int $studentId, int $targetCourseId): void
    {
        if (ExemptionDecision::query()->where('student_id', $studentId)->where('target_course_id', $targetCourseId)->exists()
            || TransferDecision::query()->where('student_id', $studentId)->where('target_course_id', $targetCourseId)->exists()
            || WaiverDecision::query()->where('student_id', $studentId)->where('target_course_id', $targetCourseId)->exists()) {
            throw new ConflictHttpException(__('credit_decisions.effective_decision_exists'));
        }
    }

    private function assertNoTargetAttempt(int $studentId, int $targetCourseId): void
    {
        $exists = Enrollment::query()
            ->where('student_id', $studentId)
            ->whereNotNull('earned')
            ->whereHas('offering', fn ($query) => $query->where('course_id', $targetCourseId))
            ->get()
            ->contains(fn (Enrollment $enrollment): bool => $enrollment->course_status->burnsRetakeAllowance());
        if ($exists) {
            throw new ConflictHttpException(__('credit_decisions.waiver.target_attempt_exists'));
        }
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
            throw new ConflictHttpException(__('credit_decisions.waiver.student_inactive'));
        }
    }

    private function assertRevision(WaiverRequest $request, int $expected): void
    {
        if ($request->revision !== $expected) {
            throw new ConflictHttpException(__('credit_decisions.waiver.stale_request'));
        }
    }

    private function authorizeHead(WaiverRequest $request, int $actorUserId): void
    {
        $course = $this->courses->find($request->target_course_id);
        abort_unless($course !== null && in_array($actorUserId, $this->heads->headsForDepartment($course->departmentId), true), 403);
    }

    /** @return list<int> */
    private function headsFor(WaiverRequest $request): array
    {
        $course = $this->courses->find($request->target_course_id);
        abort_if($course === null, 409, __('credit_decisions.target_unavailable'));

        return $this->heads->headsForDepartment($course->departmentId);
    }

    /** @return array<string, mixed> */
    private function payload(WaiverRequest $request): array
    {
        return ['waiver_request_id' => $request->getKey(), 'course_code' => $request->target_course_code, 'revision' => $request->revision];
    }

    /** @param array<string, mixed>|null $verification */
    private function transition(WaiverRequest $request, ?WaiverRequestStatus $from, WaiverRequestStatus $to, string $action, int $actor, ?string $reason, ?string $actionId, ?array $verification = null): WaiverRequestTransition
    {
        return WaiverRequestTransition::query()->create([
            'waiver_request_id' => $request->getKey(), 'from_status' => $from, 'to_status' => $to,
            'action' => $action, 'actor_user_id' => $actor, 'revision' => $request->revision,
            'evidence_snapshot' => $request->evidence, 'verification_snapshot' => $verification,
            'action_id' => $actionId, 'reason' => $reason, 'created_at' => now(),
        ]);
    }

    private function replayed(WaiverRequest $request, int $actor, string $actionId, string $action): ?WaiverRequest
    {
        $transition = WaiverRequestTransition::query()->where('action_id', $actionId)->first();
        if ($transition === null) {
            return null;
        }
        if ($transition->waiver_request_id !== $request->getKey() || $transition->actor_user_id !== $actor || $transition->action !== $action) {
            throw new ConflictHttpException(__('credit_decisions.waiver.stale_request'));
        }

        return $request->fresh(['transitions', 'decision']);
    }

    /** @return array<string, mixed>|null */
    private function verification(WaiverRequest $request): ?array
    {
        return $request->verified_by === null ? null : [
            'coverage_percentage' => $request->coverage_percentage,
            'equivalency_note' => $request->equivalency_note,
            'verified_by' => $request->verified_by,
            'verified_at' => $request->verified_at?->toIso8601String(),
        ];
    }

    private function audit(WaiverRequest $request, string $action, int $actor, ?string $reason = null): void
    {
        $this->audit->record('waiver.request.'.$action, $actor, array_filter([
            'student_id' => $request->student_id, 'target_course_id' => $request->target_course_id,
            'revision' => $request->revision, 'reason' => $reason,
        ], static fn (mixed $value): bool => $value !== null), 'waiver_request', (string) $request->getKey());
    }
}
