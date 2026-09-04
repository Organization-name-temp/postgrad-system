<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\NotifiesApplicant;
use Modules\Admissions\Internal\Http\Concerns\ResolvesQueuePrograms;
use Modules\Admissions\Internal\Http\Concerns\ScopesToDeanFaculty;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Support\AdmissionAcceptanceEvidenceFactory;
use Modules\Admissions\Public\Events\AdmissionAccepted;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;
use Modules\Notifications\Public\Contracts\Notifier;

/**
 * @group Admissions
 *
 * The Dean's final decision (ticket 10) — the phase's climax. After the interview
 * (`interview_completed`) the Dean records one of four outcomes: **Accepted**,
 * **Conditionally Accepted** (with conditions), **Waitlisted** (with a position), or
 * **Rejected** (with a reason). Accepting is the terminal act that **mints a Student**
 * — a University ID, the Student role in place of Applicant, an assigned advisor, and
 * a `Student` anchor — all atomically through the Identity\Public
 * {@see StudentEnrollment} facade (ADR-0013); no Student exists unless the application
 * is Accepted. Conditional and waitlist outcomes are held, and the same endpoint later
 * **resolves** them to Accepted (which mints) or Rejected — the two extra exits the
 * transition map allows (spec §Status machine).
 *
 * The surface is gated on `applicant.decide` (Dean only). Faculty routing is enforced,
 * not assumed: as for Dean review, an application whose primary Program is outside the
 * Dean's assigned Faculties is refused with a 403, whatever the action
 * ({@see ScopesToDeanFaculty}). The decision form pre-fills the least-loaded advisor
 * ({@see self::advisors()}), overridable to any R03 user (validated at accept).
 */
class FinalDecisionController
{
    use NotifiesApplicant;
    use ResolvesQueuePrograms;
    use ScopesToDeanFaculty;

    /**
     * Dean decisions queue.
     *
     * The applications awaiting the signed-in Dean's final decision — those resting at
     * `interview_completed` (a first decision is due) and those held at
     * `conditionally_accepted` / `waitlisted` (a resolution is due) — whose primary
     * Program's Faculty is among the Dean's assigned Faculties, oldest-first (by when
     * they were finalized), each with the applicant's identity, resolved primary
     * Program, and days waited in the current status. Same faculty-scoping as the Dean
     * review queue (ADR-0012); a Dean with no assigned Faculty sees an empty queue.
     * Gated on `applicant.decide` (Dean only).
     *
     * @response 200 {"data": [{"id": 1, "status": "interview_completed", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-12T10:00:00+00:00", "age_days": 3}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(Request $request, DeanAssignments $assignments, ProgramCatalog $catalog, Directory $directory): AnonymousResourceCollection
    {
        // Post-interview applications awaiting a first decision, plus the held offers
        // awaiting resolution. An administrator sees them unscoped across every Faculty
        // (ADR-0015); a Dean sees only their own, faculty-scoped exactly like the Dean
        // review queue. The set is named once, in the trait, and shared with the
        // Admission Decisions Dashboard Card that counts it (phase 4.5, ticket 08).
        $queue = $this->awaitingDecisionQueue($request, $assignments, $catalog, $directory);

        return ApplicationQueueResource::collection($queue);
    }

    /**
     * Advisors for the decision form.
     *
     * The academic advisors (R03) the Dean may assign, with the least-loaded one
     * pre-selected in `meta.suggested_advisor_id` (null when no advisor exists). Gated
     * on `applicant.decide`.
     *
     * @response 200 {"data": [{"id": 3, "name": "Dr. Advisor", "email": "advisor@ejust.test"}], "meta": {"suggested_advisor_id": 3}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function advisors(Directory $directory, StudentEnrollment $enrollment): JsonResponse
    {
        $advisors = $directory->usersByRole(Role::Advisor);
        $suggested = $enrollment->suggestAdvisor();

        return response()->json([
            'data' => array_map(
                static fn (UserSummary $advisor): array => $advisor->toArray(),
                $advisors,
            ),
            'meta' => ['suggested_advisor_id' => $suggested?->id],
        ]);
    }

    /**
     * Record the final decision.
     *
     * `accept` mints the Student (University ID on `meta.university_id`, R01→R02, advisor
     * assigned) and works from `interview_completed` as the first decision, or from
     * `conditionally_accepted`/`waitlisted` as a resolution. `conditional` and `waitlist`
     * hold the application (recording `conditions` / `waitlist_position`); `reject` ends
     * it. The application must belong to one of the Dean's Faculties (else 403); an action
     * illegal from the current status is a 409. Gated on `applicant.decide`.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @bodyParam action string required One of accept, conditional, waitlist, reject. Example: accept
     * @bodyParam advisor_id integer The advisor to assign on accept; defaults to the least-loaded advisor. Example: 3
     * @bodyParam conditions string required when action is conditional. The offer's conditions. Example: Submit the final transcript by September.
     * @bodyParam waitlist_position integer required when action is waitlist. The applicant's place. Example: 2
     * @bodyParam note string required when action is reject. The reason. Example: The panel did not recommend admission.
     *
     * @response 200 scenario="accepted (Student minted)" {"data": {"id": 1, "status": "accepted"}, "meta": {"university_id": "2026-MSC-CE-0001"}}
     * @response 200 scenario="conditionally accepted" {"data": {"id": 1, "status": "conditionally_accepted", "conditions": "Submit the final transcript by September."}}
     * @response 200 scenario="waitlisted" {"data": {"id": 1, "status": "waitlisted", "waitlist_position": 2}}
     * @response 200 scenario="rejected" {"data": {"id": 1, "status": "rejected"}}
     * @response 403 scenario="out of faculty" {"message": "This action is unauthorized."}
     * @response 409 scenario="not decidable from current status" {"message": "This application cannot move from under_dean_review to accepted."}
     * @response 422 scenario="advisor is not an advisor" {"message": "The selected advisor must be an academic advisor.", "errors": {"advisor_id": ["The selected advisor must be an academic advisor."]}}
     */
    public function decide(
        Request $request,
        Application $application,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
        StudentEnrollment $enrollment,
        Directory $directory,
        Notifier $notifier,
        AdmissionAcceptanceEvidenceFactory $evidenceFactory,
    ): JsonResource {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['accept', 'conditional', 'waitlist', 'reject'])],
            'advisor_id' => ['nullable', 'integer'],
            'conditions' => ['required_if:action,conditional', 'nullable', 'string', 'max:2000'],
            'waitlist_position' => ['required_if:action,waitlist', 'nullable', 'integer', 'min:1'],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ]);

        // Routing integrity before state: an application outside this Dean's Faculties
        // is refused (403) whatever the action. An administrator bypasses the scope
        // (ADR-0015).
        $this->authorizeDeanFacultyScope($request, $application, $assignments, $catalog, $directory);

        $actorId = (int) $request->user()?->getAuthIdentifier();

        if ($validated['action'] === 'accept') {
            $advisorId = $request->filled('advisor_id') ? (int) $request->input('advisor_id') : null;

            return $this->accept($application, $actorId, $advisorId, $catalog, $enrollment, $directory, $evidenceFactory);
        }

        // The held (conditional/waitlist) and terminal-reject outcomes: a guarded status
        // change plus one applicant notification, committed together so the applicant is
        // never told of a decision that did not persist.
        DB::transaction(function () use ($application, $actorId, $validated, $notifier): void {
            switch ($validated['action']) {
                case 'conditional':
                    $conditions = (string) $validated['conditions'];
                    $application->acceptConditionally($actorId, $conditions);
                    $this->notifyDecisionRecorded($notifier, $application, ApplicationStatus::ConditionallyAccepted, $conditions);
                    break;
                case 'waitlist':
                    $application->waitlist($actorId, (int) $validated['waitlist_position']);
                    $this->notifyDecisionRecorded($notifier, $application, ApplicationStatus::Waitlisted, null);
                    break;
                default: // reject
                    $reason = (string) $validated['note'];
                    $application->reject($actorId, $reason);
                    $this->notifyDecisionRecorded($notifier, $application, ApplicationStatus::Rejected, $reason);
            }
        });

        return new ApplicationResource($application);
    }

    /**
     * The terminal `Accepted` transition and the acceptance mint (ADR-0013). Resolves
     * the advisor (the Dean's override, validated to hold R03, or the least-loaded
     * suggestion), builds the {@see AdmitStudent} command from the application's cycle
     * and frozen identity snapshot, and runs the status change and
     * {@see StudentEnrollment::admit()} in one transaction — so an `Accepted` application
     * and its Student commit together. The `AdmissionAccepted` event fires only after the
     * commit, carrying the minted University ID to the notification listener.
     */
    private function accept(
        Application $application,
        int $actorId,
        ?int $advisorId,
        ProgramCatalog $catalog,
        StudentEnrollment $enrollment,
        Directory $directory,
        AdmissionAcceptanceEvidenceFactory $evidenceFactory,
    ): JsonResource {
        $result = DB::transaction(function () use (
            $application,
            $actorId,
            $advisorId,
            $catalog,
            $enrollment,
            $directory,
            $evidenceFactory,
        ) {
            $programId = $application->primary_program_id;
            $program = $programId === null ? null : $catalog->find($programId);

            if ($program === null) {
                throw ValidationException::withMessages(['action' => __('admissions.decision.no_program')]);
            }

            $admittedAt = CarbonImmutable::now();
            $curriculumVersion = $catalog->curriculumVersionEffectiveAt($program->id, $admittedAt);
            if ($curriculumVersion === null) {
                throw ValidationException::withMessages(['action' => __('admissions.decision.no_curriculum_version')]);
            }

            $resolvedAdvisorId = $this->resolveAdvisor($advisorId, $enrollment, $directory);
            $cycle = $application->admissionCycle()->firstOrFail();
            $evidenceFacts = $evidenceFactory->facts($application, $program);

            $command = new AdmitStudent(
                userId: (int) $application->user_id,
                actorUserId: $actorId,
                programId: $program->id,
                programCode: $program->code,
                curriculumVersionId: $curriculumVersion->id,
                admissionYear: (int) $cycle->year,
                enrollmentTerm: $cycle->intake_term->value.' '.$cycle->year,
                admissionCycleId: (int) $application->admission_cycle_id,
                advisorId: $resolvedAdvisorId,
                admittedAt: $admittedAt,
                identity: $application->identitySnapshot(),
                admissionEvidence: $evidenceFacts,
            );

            // Application::accept joins this transaction, so version resolution, status,
            // Student anchor and role swap commit or roll back as one decision.
            return [
                $application->accept($actorId, $enrollment, $command),
                $evidenceFacts,
            ];
        });

        [$admittedStudent, $evidenceFacts] = $result;

        // Side-effect only, after the mint commits: the acceptance notification (ADR-0013).
        Event::dispatch(new AdmissionAccepted(
            evidence: $evidenceFactory->fromFacts($admittedStudent->studentId, $evidenceFacts),
            universityId: $admittedStudent->universityId,
        ));

        return (new ApplicationResource($application))
            ->additional(['meta' => ['university_id' => $admittedStudent->universityId]]);
    }

    /**
     * The advisor id to assign on accept. An explicit `$advisorId` is validated to hold
     * R03 (else 422); absent one, the least-loaded advisor is used (422 when none
     * exists). The result is trusted by the mint — the sole place R03 membership is
     * asserted for the assigned advisor.
     */
    private function resolveAdvisor(?int $advisorId, StudentEnrollment $enrollment, Directory $directory): int
    {
        $advisorIds = array_map(
            static fn (UserSummary $advisor): int => $advisor->id,
            $directory->usersByRole(Role::Advisor),
        );

        if ($advisorId !== null) {
            if (! in_array($advisorId, $advisorIds, true)) {
                throw ValidationException::withMessages([
                    'advisor_id' => __('admissions.decision.advisor_not_r03'),
                ]);
            }

            return $advisorId;
        }

        $suggested = $enrollment->suggestAdvisor();

        if ($suggested === null) {
            throw ValidationException::withMessages([
                'advisor_id' => __('admissions.decision.no_advisor'),
            ]);
        }

        return $suggested->id;
    }
}
