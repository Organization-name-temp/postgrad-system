<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Concerns\ScopesToAdvisees;
use Modules\Enrollment\Internal\Http\Resources\RegistrationRequestResource;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartValidator;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;
use Modules\Enrollment\Internal\Support\EligibilityResolver;
use Modules\Enrollment\Internal\Support\ExistingEnrollments;
use Modules\Enrollment\Internal\Support\MintPlanEntry;
use Modules\Enrollment\Internal\Support\RetakeDeriver;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Enrollment
 *
 * The Advisor's review of a Student's registration (ADR-0017, ticket 06) — the heart of the
 * phase, where a Pending request becomes real Enrollments. Gated on `registration.approve`
 * (Advisor R03 / Student Affairs R05). An Advisor sees a **live-scoped** queue of their
 * advisees' requests and decides one **line by line** — approving or trimming each line
 * with a note, never adding; Student Affairs (and administrators) review **unscoped** to
 * unblock any request ({@see ScopesToAdvisees}).
 *
 * Approving **re-validates** the approved subset at decision time — the same batch rules the
 * cart enforced ({@see CartValidator}) plus the checks that need the Student's record: the
 * composition caps and duplicate/clash against existing Enrollments, and the derived Retake
 * ({@see RetakeDeriver}) with its cap of one. A clean subset **mints one Enrollment per
 * approved line atomically in the decision transaction** (the ADR-0013 accept→mint
 * precedent); ≥1 approved line ⇒ the request is Approved, all rejected ⇒ Rejected. The
 * Student is notified either way ({@see NotifiesRegistration}). Every cross-module fact is
 * read through a Public facade (no FK, ADR-0012).
 */
class RegistrationReviewController
{
    use CountsEnrollments;
    use NotifiesRegistration;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;
    use ScopesToAdvisees;

    /**
     * The advisor's registration queue.
     *
     * The Pending registration requests awaiting the signed-in reviewer, oldest-first — an
     * Advisor's own **live** advisee set (a request follows the Student when their advisor
     * is reassigned), or, for Student Affairs / an administrator, every advisor's advisees
     * unscoped (user story 52). Each row carries the Student's context — name, University
     * ID, degree, Program, enrollment term — and the number of courses requested, so the
     * reviewer can triage. Gated on `registration.approve`.
     *
     * @response 200 {"data": [{"id": 1, "semester_id": 1, "kind": "Initial", "submitted_at": "2026-08-02T09:00:00+00:00", "course_count": 2, "student": {"student_id": 5, "name": "Sara Student", "university_id": "2026-MSC-CE-0001", "enrollment_term": "Fall 2026", "degree_level": "MSc", "program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc"}}}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function queue(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $scope = $this->adviseeScope($userId, $directory, $students);

        $query = RegistrationRequest::query()->pending()->orderBy('created_at')->orderBy('id');
        if ($scope !== null) {
            $query->whereIn('student_id', $scope);
        }

        /** @var Collection<int, RegistrationRequest> $requests */
        $requests = $query->withCount('lines')->get();

        if ($requests->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $studentSummaries = $students->findManyByStudentId(
            $requests->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
        [$users, $programSummaries] = $this->resolveStudentRefs($studentSummaries, $directory, $programs);

        $rows = $requests->map(fn (RegistrationRequest $registration): array => [
            'id' => $registration->getKey(),
            'semester_id' => $registration->semester_id,
            'kind' => $registration->kind->value,
            'submitted_at' => $registration->created_at->toIso8601String(),
            'course_count' => (int) $registration->getAttribute('lines_count'),
            'student' => $this->studentContext(
                $studentSummaries[$registration->student_id] ?? null,
                $programSummaries,
                $users,
                currentLoad: null,
            ),
        ])->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * A single request for line-item review.
     *
     * The full request for the review screen: the Student's context beside the lines —
     * Program, degree, enrollment term, and current semester load — and every line with its
     * classification, credit hours, meeting times and a **derived Retake** preview (whether
     * approving it would mint a Retake from the Student's record). Same advisee scope as the
     * queue: an Advisor may open only their own advisee's request (else 403). Gated on
     * `registration.approve`.
     *
     * @urlParam registration_id integer required The registration request's id. Example: 1
     *
     * @response 200 {"data": {"request": {"id": 1, "semester_id": 1, "kind": "Initial", "status": "Pending", "advisor_note": null, "decided_at": null, "submitted_at": "2026-08-02T09:00:00+00:00", "lines": [{"id": 1, "offering_id": 1, "mode": "Regular", "decision": null, "derived_retake": false, "classification": "Core", "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}, "student": {"student_id": 5, "name": "Sara Student", "university_id": "2026-MSC-CE-0001", "enrollment_term": "Fall 2026", "degree_level": "MSc", "current_load": 0, "program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc"}}}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function show(
        Request $request,
        RegistrationRequest $registration,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
        CourseCatalog $courses,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $this->authorizeAdviseeScope($userId, (int) $registration->student_id, $directory, $students);

        $student = $students->findByStudentId((int) $registration->student_id);
        abort_if($student === null, 404);

        $registration->load('lines.offering.meetings');
        $this->hydrateLines($registration, $student, $courses, $directory, $eligibility);

        // Preview the derived Retake per line from the Student's record, so the Advisor sees
        // the label before deciding (the mint re-derives authoritatively).
        $existing = $this->existingEnrollments((int) $registration->student_id, (int) $registration->semester_id);
        $registration->lines->each(function (RegistrationRequestLine $line) use ($existing): void {
            $offering = $line->offering;
            $courseId = $offering === null ? null : (int) $offering->course_id;
            $line->setAttribute(
                'derived_retake',
                $courseId !== null && $existing->priorForCreditFor($courseId) >= 1,
            );
        });

        $currentLoad = $this->currentLoad((int) $registration->student_id, (int) $registration->semester_id);

        return response()->json([
            'data' => [
                'request' => (new RegistrationRequestResource($registration))->resolve(),
                'student' => $this->studentContext(
                    $student,
                    $programs->findMany([$student->programId]),
                    $directory->findMany([$student->userId]),
                    currentLoad: $currentLoad,
                ),
            ],
        ]);
    }

    /**
     * Record the Advisor's line-item decision.
     *
     * Approve or reject each line and attach an optional note. The approved subset is
     * re-validated at decision time — credit-hour floor (waived in the Student's first
     * semester) and ceiling, composition caps and duplicate/clash against the Student's
     * existing Enrollments, seat capacity, and the derived-Retake cap of one — and, if
     * clean, **one Enrollment is minted per approved line atomically**. ≥1 approved line ⇒
     * the request is Approved; all rejected ⇒ Rejected. The request must still be Pending
     * (else 409) and, for an Advisor, belong to one of their advisees (else 403). Gated on
     * `registration.approve`.
     *
     * @urlParam registration_id integer required The registration request's id. Example: 1
     *
     * @bodyParam note string An optional note for the Student. Example: Trimmed one clashing course.
     * @bodyParam lines object[] required One entry per line of the request — every line must be addressed.
     * @bodyParam lines[].id integer required The request line's id. Example: 1
     * @bodyParam lines[].decision string required approved or rejected. Example: approved
     *
     * @response 200 scenario="approved (Enrollments minted)" {"data": {"id": 1, "semester_id": 1, "kind": "Initial", "status": "Approved", "advisor_note": "Looks good.", "decided_at": "2026-08-03T09:00:00+00:00", "submitted_at": "2026-08-02T09:00:00+00:00", "lines": [{"id": 1, "offering_id": 1, "mode": "Regular", "decision": "approved", "derived_retake": false, "classification": "Core", "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 scenario="not this advisor's advisee" {"message": "This action is unauthorized."}
     * @response 409 scenario="already decided" {"message": "This registration request has already been decided."}
     * @response 422 scenario="line ids do not match the request" {"message": "Every line of the request must be decided.", "errors": {"lines": ["Every line of the request must be decided."]}}
     * @response 422 scenario="approved subset invalid" {"message": "Your registration totals 3 credit hours, below the 6-hour minimum.", "errors": {"below_min_credit_hours": ["Your registration totals 3 credit hours, below the 6-hour minimum."]}}
     */
    public function decide(
        Request $request,
        RegistrationRequest $registration,
        StudentDirectory $students,
        Directory $directory,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        EligibilityResolver $eligibility,
        Notifier $notifier,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();

        // Scope before state: an Advisor may decide only their own advisee's request.
        $this->authorizeAdviseeScope($userId, (int) $registration->student_id, $directory, $students);

        if (! $registration->isPending()) {
            throw new ConflictHttpException('This registration request has already been decided.');
        }

        $registration->load('lines.offering.meetings');

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.decision' => ['required', Rule::enum(LineDecision::class)],
        ]);

        $decisions = $this->decisionsByLineId($validated['lines'], $registration);
        $note = $validated['note'] ?? null;

        $student = $students->findByStudentId((int) $registration->student_id);
        abort_if($student === null, 404);

        $approvedLines = $registration->lines->filter(
            fn (RegistrationRequestLine $line): bool => $decisions[$line->getKey()] === LineDecision::Approved,
        )->values();

        // All rejected — no mint, request Rejected.
        if ($approvedLines->isEmpty()) {
            $this->persistDecision($registration, $decisions, RegistrationStatus::Rejected, $userId, $note, mintPlan: []);
            $this->afterDecision($notifier, $registration, $student, RegistrationStatus::Rejected, approvedCount: 0, rejectedCount: $registration->lines->count());

            return $this->decidedResponse($registration, $student, $courses, $directory, $eligibility);
        }

        $semester = $calendar->find((int) $registration->semester_id);
        abort_if($semester === null, 404);

        [$cartLines, $ineligible] = $this->resolveApprovedLines($approvedLines, $student, $courses, $directory, $eligibility);
        $existing = $this->existingEnrollments((int) $registration->student_id, (int) $registration->semester_id);

        // $cartLines is keyed by request-line id (for the mint plan below); the batch
        // validator wants a plain list.
        $validator = new CartValidator(CreditHoursValidator::fromConfig());
        $violations = $validator->validate(array_values($cartLines), $this->exceptionsFor($student, $semester), $existing);

        // Derive each approved line's status (Regular/Retake/Audit); a second retake blocks.
        $deriver = new RetakeDeriver;
        $mintPlan = [];
        foreach ($cartLines as $lineId => $cartLine) {
            $derivation = $deriver->derive($cartLine->mode, $existing->priorForCreditFor($cartLine->courseId));
            if ($derivation->blocked) {
                $violations[] = new CartViolation(
                    CartViolation::RETAKE_LIMIT,
                    "{$cartLine->courseCode} has already used its one allowed retake.",
                    [$cartLine->offeringId],
                );

                continue;
            }
            $mintPlan[$lineId] = new MintPlanEntry($cartLine, $derivation->status);
        }

        $errors = $this->errorsFrom($violations, $ineligible);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->persistDecision($registration, $decisions, RegistrationStatus::Approved, $userId, $note, $mintPlan);
        $this->afterDecision($notifier, $registration, $student, RegistrationStatus::Approved, approvedCount: count($mintPlan), rejectedCount: $registration->lines->count() - count($mintPlan));

        return $this->decidedResponse($registration, $student, $courses, $directory, $eligibility);
    }

    /**
     * Map the submitted decisions to `line id => LineDecision`, asserting the set of ids
     * exactly matches the request's lines (every line addressed, no strangers) — an
     * Advisor decides the whole request, never a partial slice.
     *
     * @param  array<int, array{id: int, decision: string}>  $submitted
     * @return array<int, LineDecision>
     */
    private function decisionsByLineId(array $submitted, RegistrationRequest $registration): array
    {
        $decisions = [];
        foreach ($submitted as $entry) {
            $decisions[(int) $entry['id']] = LineDecision::from($entry['decision']);
        }

        $requestLineIds = $registration->lines->map(fn (RegistrationRequestLine $line): int => $line->getKey())->sort()->values()->all();
        $submittedIds = collect(array_keys($decisions))->sort()->values()->all();

        if ($requestLineIds !== $submittedIds) {
            throw ValidationException::withMessages([
                'lines' => 'Every line of the request must be decided.',
            ]);
        }

        return $decisions;
    }

    /**
     * Persist the decision and (on approval) mint the Enrollments — all in one transaction,
     * so an Approved request and its Enrollments commit together (ADR-0013). Sets the
     * request's terminal status + note + decider, stamps each line's outcome, and creates
     * one Enrollment per mint-plan entry snapshotting its classification, derived status and
     * registration-time credit hours (ADR-0017).
     *
     * @param  array<int, LineDecision>  $decisions  line id => outcome
     * @param  array<int, MintPlanEntry>  $mintPlan  line id => plan
     */
    private function persistDecision(
        RegistrationRequest $registration,
        array $decisions,
        RegistrationStatus $outcome,
        int $decidedBy,
        ?string $note,
        array $mintPlan,
    ): void {
        DB::transaction(function () use ($registration, $decisions, $outcome, $decidedBy, $note, $mintPlan): void {
            $registration->recordDecision($outcome, $decidedBy, $note);

            $registration->lines->each(function (RegistrationRequestLine $line) use ($decisions): void {
                $line->update(['decision' => $decisions[$line->getKey()]]);
            });

            foreach ($mintPlan as $plan) {
                Enrollment::create([
                    'student_id' => $registration->student_id,
                    'course_offering_id' => $plan->cartLine->offeringId,
                    'classification' => $plan->cartLine->classification,
                    'course_status' => $plan->status,
                    'credit_hours' => $plan->cartLine->totalCh,
                ]);
            }
        });
    }

    /**
     * The side effect after the decision commits: notify the Student of the outcome and its
     * per-line tally (never before the transaction, so no notification of an unpersisted
     * decision).
     */
    private function afterDecision(
        Notifier $notifier,
        RegistrationRequest $registration,
        StudentSummary $student,
        RegistrationStatus $outcome,
        int $approvedCount,
        int $rejectedCount,
    ): void {
        $this->notifyRegistrationDecided($notifier, $student->userId, $registration, $outcome, $approvedCount, $rejectedCount);
    }

    /**
     * The decided request as the response — reloaded with its lines' decisions and hydrated
     * display facts so the SPA renders the outcome per line.
     */
    private function decidedResponse(
        RegistrationRequest $registration,
        StudentSummary $student,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $registration->load('lines.offering.meetings');
        $this->hydrateLines($registration, $student, $courses, $directory, $eligibility);

        return response()->json([
            'data' => (new RegistrationRequestResource($registration))->resolve(),
        ]);
    }

    /**
     * Resolve the approved lines into {@see CartLine}s for re-validation, keyed by their
     * request-line id. Any line whose Offering dropped or no longer classifies for the
     * Student (a curriculum edit since submit) is collected as ineligible.
     *
     * @param  Collection<int, RegistrationRequestLine>  $approvedLines
     * @return array{0: array<int, CartLine>, 1: array<int, int>} [line id => cart line, line id => offering id]
     */
    private function resolveApprovedLines(
        Collection $approvedLines,
        StudentSummary $student,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): array {
        $offerings = $approvedLines->pluck('offering')->filter()->keyBy('id');
        $this->attachOfferingRefs($offerings, $courses, $directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $eligibility->classify($student, $offerings);

        $cartLines = [];
        $ineligible = [];

        foreach ($approvedLines as $line) {
            $offeringId = (int) $line->course_offering_id;
            $offering = $offerings->get($offeringId);
            $classification = $classifications[$offeringId] ?? null;
            $course = $offering?->getAttribute('resolved_course');

            if ($offering === null || $classification === null || ! $course instanceof CourseSummary) {
                $ineligible[$line->getKey()] = $offeringId;

                continue;
            }

            $cartLines[$line->getKey()] = new CartLine(
                offeringId: $offeringId,
                courseId: (int) $offering->course_id,
                courseCode: $course->code,
                mode: $line->mode,
                totalCh: $course->totalCh,
                classification: $classification,
                seatsRemaining: $offering->seatsRemaining(),
                meetings: $this->meetingSlots($offering),
            );
        }

        return [$cartLines, $ineligible];
    }

    /**
     * The Student's already-minted Enrollment facts for mint-time re-validation
     * ({@see ExistingEnrollments}): every enrolled Offering (duplicate), active counts by
     * classification (caps), prior for-credit attempts per course (retake), and the active
     * schedule in the target Semester (clash). One read of the Student's Enrollments.
     */
    private function existingEnrollments(int $studentId, int $targetSemesterId): ExistingEnrollments
    {
        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->where('student_id', $studentId)
            ->with('offering.meetings')
            ->get();

        $active = $enrollments->filter(
            fn (Enrollment $enrollment): bool => $enrollment->course_status !== CourseStatus::Withdrawn,
        );

        $priorForCredit = [];
        $meetings = [];
        foreach ($active as $enrollment) {
            $offering = $enrollment->offering;
            if ($offering === null) {
                continue;
            }
            if ($enrollment->course_status->burnsRetakeAllowance()) {
                $courseId = (int) $offering->course_id;
                $priorForCredit[$courseId] = ($priorForCredit[$courseId] ?? 0) + 1;
            }
            if ((int) $offering->semester_id === $targetSemesterId) {
                foreach ($this->meetingSlots($offering) as $slot) {
                    $meetings[] = $slot;
                }
            }
        }

        return new ExistingEnrollments(
            enrolledOfferingIds: $enrollments->pluck('course_offering_id')->map(fn ($id): int => (int) $id)->all(),
            externalElectiveCount: $active->filter(fn (Enrollment $e): bool => $e->classification === EnrollmentClassification::ExternalElective)->count(),
            researchCourseCount: $active->filter(fn (Enrollment $e): bool => $e->classification === EnrollmentClassification::ResearchCourse)->count(),
            priorForCreditCountByCourse: $priorForCredit,
            meetings: $meetings,
        );
    }

    /** The Student's current active credit-hour load in the given Semester. */
    private function currentLoad(int $studentId, int $semesterId): int
    {
        return (int) Enrollment::query()
            ->where('student_id', $studentId)
            ->active()
            ->whereHas('offering', fn ($query) => $query->where('semester_id', $semesterId))
            ->sum('credit_hours');
    }

    /**
     * Attach each line's resolved Course/Instructor and derived classification onto its
     * Offering, so {@see RegistrationRequestResource} renders live display facts (ADR-0012)
     * — one batched facade read across the request's lines.
     */
    private function hydrateLines(
        RegistrationRequest $registration,
        StudentSummary $student,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): void {
        /** @var Collection<int, CourseOffering> $offerings */
        $offerings = $registration->lines->pluck('offering')->filter()->values();
        if ($offerings->isEmpty()) {
            return;
        }

        $this->attachOfferingRefs($offerings, $courses, $directory);
        $classifications = $eligibility->classify($student, $offerings);

        $offerings->each(fn (CourseOffering $offering) => $offering->setAttribute(
            'classification',
            $classifications[$offering->getKey()] ?? null,
        ));
    }

    /**
     * Map the cart violations and ineligible lines to a Laravel validation error bag — cart
     * violations keyed by their stable code, ineligible lines by their offering id.
     *
     * @param  list<CartViolation>  $violations
     * @param  array<int, int>  $ineligible  line id => offering id
     * @return array<string, list<string>>
     */
    private function errorsFrom(array $violations, array $ineligible): array
    {
        $errors = [];

        foreach ($ineligible as $offeringId) {
            $errors['lines'][] = "An approved course (offering {$offeringId}) is no longer available for this student.";
        }

        foreach ($violations as $violation) {
            $errors[$violation->code][] = $violation->message;
        }

        return $errors;
    }

    /**
     * Resolve the owning users and Programs for a set of Student summaries in one read each.
     *
     * @param  array<int, StudentSummary>  $students
     * @return array{0: array<int, UserSummary>, 1: array<int, ProgramSummary>}
     */
    private function resolveStudentRefs(array $students, Directory $directory, ProgramCatalog $programs): array
    {
        $userIds = array_values(array_map(fn (StudentSummary $s): int => $s->userId, $students));
        $programIds = array_values(array_unique(array_map(fn (StudentSummary $s): int => $s->programId, $students)));

        return [$directory->findMany($userIds), $programs->findMany($programIds)];
    }

    /**
     * The Student context beside a request — identity, degree, Program, enrollment term, and
     * (on the review screen) current semester load. Names come from the Identity directory
     * (the Student anchor carries none, ADR-0012); the degree/Program from the Catalog.
     *
     * @param  array<int, ProgramSummary>  $programs  keyed by program id
     * @param  array<int, UserSummary>  $users  keyed by user id
     * @return array<string, mixed>
     */
    private function studentContext(?StudentSummary $student, array $programs, array $users, ?int $currentLoad): array
    {
        if ($student === null) {
            return [];
        }

        $program = $programs[$student->programId] ?? null;

        return [
            'student_id' => $student->studentId,
            'name' => isset($users[$student->userId]) ? $users[$student->userId]->name : $student->universityId,
            'university_id' => $student->universityId,
            'enrollment_term' => $student->enrollmentTerm,
            'degree_level' => $program?->degreeLevel->value,
            'current_load' => $currentLoad,
            'program' => $program === null ? null : [
                'id' => $program->id,
                'code' => $program->code,
                'name_en' => $program->nameEn,
                'name_ar' => $program->nameAr,
                'degree_level' => $program->degreeLevel->value,
            ],
        ];
    }
}
