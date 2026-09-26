<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Public\AcademicRequestCloseout;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

final readonly class EloquentAcademicRequestCloseout implements AcademicRequestCloseout
{
    public function __construct(
        private StudentDirectory $students,
        private CourseCatalog $courses,
        private DepartmentHeadAssignments $heads,
        private Directory $directory,
        private Notifier $notifier,
    ) {}

    public function forTermination(int $studentId, int $terminationCaseId): void
    {
        $student = $this->students->findByStudentId($studentId);
        if ($student === null) {
            return;
        }

        foreach (RegistrationRequest::query()->where('student_id', $studentId)->whereIn('status', [RegistrationStatus::Pending, RegistrationStatus::AwaitingCapacityOverride])->lockForUpdate()->get() as $request) {
            if (! $this->claim('registration', $request->id, $studentId, $terminationCaseId)) {
                continue;
            }
            $from = $request->status->value;
            $request->update(['status' => RegistrationStatus::Rejected, 'advisor_note' => __('academic_standing.request_closed', ['case' => $terminationCaseId]), 'decided_at' => now()]);
            DB::table('registration_request_transitions')->insert(['registration_request_id' => $request->id, 'from_status' => $from, 'to_status' => RegistrationStatus::Rejected->value, 'action' => 'system_termination_closeout', 'system_cause_type' => 'termination_case', 'system_cause_id' => $terminationCaseId, 'created_at' => now()]);
            $this->notify($student->userId, $student->advisorId, 'registration', $request->id, $terminationCaseId, []);
        }

        foreach ([[ExemptionRequest::class, 'exemption'], [TransferRequest::class, 'transfer'], [WaiverRequest::class, 'waiver']] as [$model, $type]) {
            foreach ($model::query()->where('student_id', $studentId)->whereNotIn('status', ['Approved', 'Rejected', 'Abandoned'])->lockForUpdate()->get() as $request) {
                if (! $this->claim($type, $request->id, $studentId, $terminationCaseId)) {
                    continue;
                }
                $from = $request->status;
                $request->update(['status' => 'Rejected']);
                $this->appendCreditTransition($request, $type, $from->value, $terminationCaseId);
                $reviewers = $this->reviewersFor($request, $from->value, $student->advisorId);
                $this->notify($student->userId, $student->advisorId, $type, $request->id, $terminationCaseId, $reviewers);
            }
        }
    }

    private function claim(string $type, int $requestId, int $studentId, int $caseId): bool
    {
        return DB::table('academic_request_closeouts')->insertOrIgnore(['student_id' => $studentId, 'request_type' => $type, 'request_id' => $requestId, 'termination_case_id' => $caseId, 'created_at' => now()]) === 1;
    }

    private function appendCreditTransition(ExemptionRequest|TransferRequest|WaiverRequest $request, string $type, string $from, int $caseId): void
    {
        $snapshot = match ($type) {
            'exemption' => $request->getAttribute('evidence'),
            'transfer' => $request->only(['external_institution', 'external_course_code', 'external_course_name', 'source_credit_hours', 'external_grade', 'document_ids']),
            default => $request->getAttribute('evidence'),
        };
        $attributes = ['from_status' => $from, 'to_status' => 'Rejected', 'action' => 'system_termination_closeout', 'actor_user_id' => null, 'revision' => $request->getAttribute('revision'), 'evidence_snapshot' => $snapshot, 'reason' => __('academic_standing.request_closed', ['case' => $caseId]), 'system_cause_type' => 'termination_case', 'system_cause_id' => $caseId, 'created_at' => now()];
        if ($type === 'transfer') {
            $attributes['review_snapshot'] = $request->only(['normalized_grade', 'verified_by_user_id', 'verified_at']);
        } elseif ($type === 'waiver') {
            $attributes['verification_snapshot'] = $request->only(['coverage_percentage', 'equivalency_note', 'verified_by', 'verified_at']);
        }
        $request->transitions()->create($attributes);
    }

    /** @return list<int> */
    private function reviewersFor(ExemptionRequest|TransferRequest|WaiverRequest $request, string $status, int $advisorId): array
    {
        if ($status === 'PendingAdvisor') {
            return [$advisorId];
        }
        if ($status === 'PendingCouncil') {
            return array_map(fn ($user): int => $user->id, $this->directory->usersByRole(Role::Council));
        }
        if ($status === 'PendingDepartmentHead') {
            $course = $this->courses->find((int) $request->getAttribute('target_course_id'));

            return $course === null ? [] : $this->heads->headsForDepartment($course->departmentId);
        }

        return [];
    }

    /** @param list<int> $reviewers */
    private function notify(int $studentUserId, int $advisorId, string $type, int $requestId, int $caseId, array $reviewers): void
    {
        $payload = ['request_type' => $type, 'request_id' => $requestId, 'termination_case_id' => $caseId];
        foreach (array_unique([$studentUserId, $advisorId, ...$reviewers]) as $recipient) {
            $this->notifier->notifyOnce($recipient, NotificationType::AcademicRequestClosed, "termination-closeout:{$caseId}:{$type}:{$requestId}:{$recipient}", $payload);
        }
    }
}
