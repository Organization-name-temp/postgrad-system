<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Public\Events\AcademicEvidenceChanged;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CreditDecisionRevoker
{
    public function __construct(
        private readonly StudentGuard $students,
        private readonly CourseCatalog $courses,
        private readonly DepartmentHeadAssignments $heads,
        private readonly Directory $directory,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
        private readonly RemediationObligations $remediation,
        private readonly PreparatoryCompletionCheckpoint $preparatoryCheckpoint,
    ) {}

    public function transfer(TransferDecision $decision, int $actorUserId, string $reason): TransferDecision
    {
        abort_unless($this->directory->hasRole($actorUserId, Role::Council), 403);

        /** @var TransferDecision */
        return $this->revoke(CreditDecisionType::Transfer, $decision, $actorUserId, $reason);
    }

    public function waiver(WaiverDecision $decision, int $actorUserId, string $reason): WaiverDecision
    {
        abort_unless($this->directory->hasRole($actorUserId, Role::Council), 403);

        /** @var WaiverDecision */
        return $this->revoke(CreditDecisionType::Waiver, $decision, $actorUserId, $reason);
    }

    public function exemption(ExemptionDecision $decision, int $actorUserId, string $reason): ExemptionDecision
    {
        $course = $this->courses->find($decision->target_course_id);
        abort_unless(
            $course !== null
                && in_array($actorUserId, $this->heads->headsForDepartment($course->departmentId), true),
            403,
        );

        /** @var ExemptionDecision */
        return $this->revoke(CreditDecisionType::Exemption, $decision, $actorUserId, $reason);
    }

    /**
     * @template T of Model
     *
     * @param  T  $decision
     * @return T
     */
    private function revoke(CreditDecisionType $type, Model $decision, int $actorUserId, string $reason): Model
    {
        return DB::transaction(function () use ($type, $decision, $actorUserId, $reason): Model {
            $student = $this->students->lock((int) $decision->getAttribute('student_id'));
            abort_if($student === null, 404);
            abort_if($student->lifecycleStatus !== StudentLifecycleStatus::Active, 409, __('credit_decisions.revocation.student_inactive'));

            /** @var Model $locked */
            $locked = $decision->newQuery()->lockForUpdate()->findOrFail($decision->getKey());
            if (CreditDecisionRevocation::query()
                ->where('decision_type', $type->value)
                ->where('decision_id', $locked->getKey())
                ->exists()) {
                throw new ConflictHttpException(__('credit_decisions.revocation.already_revoked'));
            }

            $revocation = CreditDecisionRevocation::query()->create([
                'decision_type' => $type->value,
                'decision_id' => $locked->getKey(),
                'student_id' => $student->studentId,
                'target_course_id' => (int) $locked->getAttribute('target_course_id'),
                'revoked_by_user_id' => $actorUserId,
                'reason' => $reason,
                'created_at' => now(),
            ]);
            if ($type === CreditDecisionType::Exemption && $this->preparatoryCheckpoint->hasPassed($student->enrollmentTerm)) {
                $this->remediation->createFromRevocation((int) $revocation->getKey(), studentGuardHeld: true);
            }
            $payload = [
                'decision_type' => $type->value,
                'decision_id' => (int) $locked->getKey(),
                'target_course_code' => (string) $locked->getAttribute('target_course_code'),
                'reason' => $reason,
            ];
            $this->notifier->notifyOnce(
                $student->userId,
                NotificationType::CreditDecisionRevoked,
                'credit-decision-revocation:'.$revocation->id.':student',
                $payload,
            );
            $this->notifier->notifyOnce(
                $student->advisorId,
                NotificationType::CreditDecisionRevoked,
                'credit-decision-revocation:'.$revocation->id.':advisor',
                [...$payload, 'audience' => 'advisor'],
            );
            $this->audit->record(
                action: 'credit_decision.revoked',
                actorUserId: $actorUserId,
                metadata: [...$payload, 'revocation_id' => $revocation->id],
                subjectType: $type->value.'_decision',
                subjectId: (string) $locked->getKey(),
            );
            DB::afterCommit(fn () => Event::dispatch(new AcademicEvidenceChanged($student->studentId, 'credit_decision_revocation', (int) $revocation->getKey())));

            return $locked->load('revocation');
        });
    }
}
