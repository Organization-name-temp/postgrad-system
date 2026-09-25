<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\Enrollment\Public\RegistrationPolicyWriter;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

final readonly class StandingReconciler
{
    public function __construct(
        private StudentGuard $students,
        private StandingRecords $standing,
        private GradingRecords $grading,
        private Notifier $notifier,
        private RegistrationPolicyWriter $registrationPolicy,
    ) {}

    public function reconcile(int $studentId, ?string $causeType = null, ?int $causeId = null): ?StandingObservation
    {
        return DB::transaction(function () use ($studentId, $causeType, $causeId): ?StandingObservation {
            $student = $this->students->lock($studentId);
            if ($student === null) {
                return null;
            }
            $current = $this->standing->currentForStudent($studentId);
            $this->registrationPolicy->recordStanding($studentId, $current->standing->value, $current->registrationCeilingCh);
            $latest = StandingObservation::query()->where('student_id', $studentId)->lockForUpdate()->latest('id')->first();
            if ($latest !== null && $latest->standing === $current->standing) {
                return null;
            }
            $attempts = $this->grading->effectiveAttemptsForStudent($studentId);
            $evidence = array_map(fn ($attempt): array => [
                $attempt->enrollmentId, $attempt->courseId, $attempt->earned, $attempt->letterGrade,
            ], $attempts);
            sort($evidence);
            $fingerprint = hash('sha256', json_encode([
                'standing' => $current->standing->value,
                'cgpa' => $current->cgpa,
                'attempts' => $evidence,
            ], JSON_THROW_ON_ERROR));
            $observation = StandingObservation::query()->firstOrCreate(
                ['student_id' => $studentId, 'evidence_fingerprint' => $fingerprint],
                [
                    'standing' => $current->standing,
                    'cause_type' => $causeType,
                    'cause_id' => $causeId,
                    'observed_at' => now(),
                ],
            );
            if (! $observation->wasRecentlyCreated) {
                return null;
            }

            $isAdverse = in_array($current->standing, [
                AcademicStanding::AcademicWarning,
                AcademicStanding::AcademicProbation,
                AcademicStanding::TerminationPending,
            ], true);
            $isRecovery = $latest !== null && $current->standing->severity() < $latest->standing->severity();
            if ($isAdverse || $isRecovery) {
                $payload = [
                    'standing' => $current->standing->value,
                    'previous_standing' => $latest?->standing->value,
                    'cgpa' => $current->cgpa,
                    'explanation' => $current->explanation,
                ];
                $key = 'standing-observation:'.$observation->id;
                $this->notifier->notifyOnce($student->userId, NotificationType::StandingChanged, $key.':student', $payload);
                $this->notifier->notifyOnce($student->advisorId, NotificationType::StandingChanged, $key.':advisor', [...$payload, 'audience' => 'advisor']);
            }

            return $observation;
        });
    }
}
