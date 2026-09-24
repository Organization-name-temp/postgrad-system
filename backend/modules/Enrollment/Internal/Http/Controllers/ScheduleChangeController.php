<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\GraduationProgress;
use Modules\AcademicStanding\Public\ProposedCourse;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ReadsExistingEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesStudent;
use Modules\Enrollment\Internal\Http\Resources\EnrollmentResource;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\AttemptDeriver;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartValidator;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;
use Modules\Enrollment\Internal\Support\EligibilityResolver;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Enrollment
 *
 * The Student's **period-gated schedule changes** (FR-REG-004, ticket 07) — the direct
 * actions the academic calendar governs *after* an approved initial registration, gated on
 * `courses.register_self` (the acting Student, R02). Every gate is read from the Calendar
 * Semester dates (ADR-0018), so the same action succeeds or is refused purely as time moves;
 * the server is the sole authority and returns a 409 outside an action's window.
 *
 * - **Free add/drop** — during the add/drop period (semester start → add/drop end) and only
 *   for a Student with an approved Initial request for that Semester, a direct action with
 *   no advisor approval (the Advisor is *notified*, not asked). An add re-uses the cart
 *   validators (eligibility, ceiling, capacity, clash, caps, derived Retake) but never an
 *   External Elective — those need a request (BR-24); a drop **hard-deletes** the Enrollment
 *   (no trace) unless it would take the load below the credit-hour floor (waived in the
 *   Student's first semester).
 * - **Withdrawal** — during the withdrawal period (late-add end → withdrawal deadline),
 *   self-serve: the Enrollment's Course Status becomes **W** (permanent, transcript-visible),
 *   with **no** credit-hour floor check, the Advisor notified. After the deadline every
 *   student-initiated change is gone.
 *
 * The **late-add** request (week 3, add-only through the advisor) is not here — it is a
 * {@see RegistrationController} cart of kind `LateAdd`, minted by the advisor decision.
 */
class ScheduleChangeController
{
    use CountsEnrollments;
    use NotifiesRegistration;
    use ReadsExistingEnrollments;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;
    use ResolvesStudent;

    /**
     * Free-add a course to the schedule (add/drop period).
     *
     * A direct add during the add/drop window, no advisor approval — the Advisor is notified.
     * The Offering must be eligible for the Student and **not** an External Elective (those
     * need a registration request, BR-24); the whole schedule is re-validated (credit-hour
     * ceiling counting the existing load, seat capacity, time clash, the composition caps,
     * and the derived-Retake cap of one). Available only while the Semester is in its
     * add/drop period (else 409) and only to a Student with an approved Initial request for
     * that Semester (else 403). Gated on `courses.register_self`.
     *
     * @bodyParam offering_id integer required An eligible Course Offering's id. Example: 1
     * @bodyParam mode string required How to take it — Regular or Audit. Example: Regular
     *
     * @response 201 {"data": {"id": 5, "offering_id": 1, "course_status": "Regular", "classification": "Core", "credit_hours": 3, "must_retake": false, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}], "attendance": {"present": 0, "recorded": 0, "percentage": 0, "at_risk": false}}}
     * @response 403 scenario="no approved initial registration" {"message": "Free add/drop needs an approved initial registration for this semester."}
     * @response 409 scenario="outside the add/drop window" {"message": "The add/drop period is not open for this course."}
     * @response 422 scenario="external elective" {"message": "An external elective can only be added through a registration request.", "errors": {"offering_id": ["An external elective can only be added through a registration request."]}}
     */
    public function add(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
        Notifier $notifier,
        StandingRecords $standing,
        GraduationProgress $graduationProgress,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        $validated = $request->validate([
            'offering_id' => ['required', 'integer'],
            'mode' => ['required', Rule::enum(RegistrationMode::class)],
        ]);

        $offering = CourseOffering::query()->whereKey((int) $validated['offering_id'])->with('meetings')->first();
        abort_if($offering === null, 404, 'That course offering does not exist.');

        $semester = $this->semesterOf($offering, $calendar);
        $this->assertFreeChangeAllowed($semester, $student->studentId);

        // Resolve + classify the single Offering (eligibility) through the Catalog facades.
        $offerings = collect([$offering]);
        $this->attachOfferingRefs($offerings, $courses, $directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $eligibility->classify($student, $offerings);

        $classification = $classifications[$offering->getKey()] ?? null;
        $course = $offering->getAttribute('resolved_course');

        if ($classification === null || ! $course instanceof CourseSummary) {
            throw ValidationException::withMessages([
                'offering_id' => 'This course is not available for you to add this semester.',
            ]);
        }
        if ($classification === EnrollmentClassification::ExternalElective) {
            throw ValidationException::withMessages([
                'offering_id' => 'An external elective can only be added through a registration request.',
            ]);
        }

        $mode = RegistrationMode::from($validated['mode']);
        $cartLine = new CartLine(
            offeringId: (int) $offering->getKey(),
            courseId: (int) $offering->course_id,
            courseCode: $course->code,
            mode: $mode,
            totalCh: $course->totalCh,
            classification: $classification,
            seatsRemaining: $offering->seatsRemaining(),
            meetings: $this->meetingSlots($offering),
        );

        $existing = $this->existingEnrollments($student->studentId, $semester->id);

        $validator = new CartValidator(CreditHoursValidator::fromConfig(
            $standing->currentForStudent($student->studentId)->registrationCeilingCh,
        ));
        $violations = $validator->validate([$cartLine], $this->exceptionsFor($student, $semester, [$cartLine], $graduationProgress), $existing);

        // Derive the status (Regular/Retake/CGPA Improvement/Audit); a third for-credit
        // attempt blocks the add.
        $derivation = (new AttemptDeriver)->derive(
            $mode,
            $existing->priorForCreditFor($cartLine->courseId),
            $existing->priorEarnedFor($cartLine->courseId),
        );
        if ($derivation->blocked) {
            $violations[] = new CartViolation(
                CartViolation::RETAKE_LIMIT,
                "{$cartLine->courseCode} has already used its one allowed re-registration.",
                [$cartLine->offeringId],
            );
        }

        if ($violations !== []) {
            throw ValidationException::withMessages($this->errorsFrom($violations));
        }

        $enrollment = Enrollment::create([
            'student_id' => $student->studentId,
            'course_offering_id' => (int) $offering->getKey(),
            'classification' => $classification,
            'course_status' => $derivation->status,
            'credit_hours' => $course->totalCh,
        ]);
        $enrollment->setRelation('offering', $offering);

        $this->notifyEnrollmentAdjusted(
            $notifier,
            $student->advisorId,
            'added',
            $course->code,
            $this->studentDisplayName($student, $directory),
        );

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(201);
    }

    /**
     * Free-drop a course from the schedule (add/drop period).
     *
     * A direct drop during the add/drop window — the Enrollment is **hard-deleted**, leaving
     * no trace (a drop is never a Course Status). Blocked when it would take the Student's
     * credit-hour load below the floor, unless the first-semester exception applies; the
     * Advisor is notified. Available only while the Semester is in its add/drop period (else
     * 409) and only to a Student with an approved Initial request for that Semester (else
     * 403); the Enrollment must be the acting Student's own (else 404). Gated on
     * `courses.register_self`.
     *
     * @urlParam enrollment_id integer required The Enrollment's id. Example: 5
     *
     * @response 204 scenario="dropped"
     * @response 403 scenario="no approved initial registration" {"message": "Free add/drop needs an approved initial registration for this semester."}
     * @response 409 scenario="outside the add/drop window" {"message": "The add/drop period is not open for this course."}
     * @response 422 scenario="would fall below the credit-hour floor" {"message": "Dropping DEP501 would leave you below the 6-hour minimum load.", "errors": {"below_min_credit_hours": ["Dropping DEP501 would leave you below the 6-hour minimum load."]}}
     */
    public function drop(
        Request $request,
        Enrollment $enrollment,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        Notifier $notifier,
        GraduationProgress $graduationProgress,
    ): Response {
        $student = $this->actingStudent($request, $students);
        $this->authorizeOwnEnrollment($enrollment, $student);

        $offering = $this->offeringOf($enrollment);
        $semester = $this->semesterOf($offering, $calendar);
        $this->assertFreeChangeAllowed($semester, $student->studentId);

        // The credit-hour floor guard: dropping must not take the active load below the
        // minimum, unless the first-semester exception waives the floor (FR-REG-001).
        $existing = $this->existingEnrollments($student->studentId, $semester->id);
        $course = $courses->find((int) $offering->course_id);
        $courseCode = $course !== null ? $course->code : "course #{$offering->course_id}";
        $min = (int) config('enrollment.min_credit_hours');
        $loadAfterDrop = $existing->activeCreditHours - (int) $enrollment->credit_hours;

        $remainingSchedule = Enrollment::query()
            ->active()
            ->with('offering')
            ->where('student_id', $student->studentId)
            ->whereKeyNot($enrollment->getKey())
            ->whereHas('offering', fn ($query) => $query->where('semester_id', $semester->id))
            ->get()
            ->map(fn (Enrollment $line): ProposedCourse => new ProposedCourse(
                courseId: (int) $line->offering->course_id,
                category: match ($line->classification) {
                    EnrollmentClassification::Preparatory => CreditHourCategory::Preparatory,
                    EnrollmentClassification::Core => CreditHourCategory::ProgramCore,
                    default => CreditHourCategory::ResearchElective,
                },
                creditHours: $line->credit_hours,
                forCredit: $line->course_status !== CourseStatus::Audit,
            ))->all();
        $underload = $graduationProgress->underloadEligibility(
            $student->studentId,
            $student->enrollmentTerm === $semester->nameEn,
            $remainingSchedule,
        );

        if (! $underload->waivesFloor() && $loadAfterDrop < $min) {
            throw ValidationException::withMessages([
                CartViolation::BELOW_MIN_CREDIT_HOURS => "Dropping {$courseCode} would leave you below the {$min}-hour minimum load.",
            ]);
        }

        // A drop leaves no trace (ADR-0017) — a hard delete, not a Course Status.
        $enrollment->delete();

        $this->notifyEnrollmentAdjusted(
            $notifier,
            $student->advisorId,
            'dropped',
            $courseCode,
            $this->studentDisplayName($student, $directory),
        );

        return response()->noContent();
    }

    /**
     * Withdraw from a course (withdrawal period).
     *
     * Self-serve withdrawal until the deadline: the Enrollment's Course Status becomes **W**
     * — permanent and transcript-visible — with **no** credit-hour floor check (a Student is
     * never trapped in a course to preserve a number), the Advisor notified. Available only
     * while the Semester is in its withdrawal period (else 409); the Enrollment must be the
     * acting Student's own and still active (else 404 / 409). Gated on `courses.register_self`.
     *
     * @urlParam enrollment_id integer required The Enrollment's id. Example: 5
     *
     * @response 200 {"data": {"id": 5, "offering_id": 1, "course_status": "Withdrawn", "classification": "Core", "credit_hours": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}], "attendance": {"present": 0, "recorded": 0, "percentage": 0, "at_risk": false}}}
     * @response 409 scenario="outside the withdrawal window" {"message": "The withdrawal period is not open for this course."}
     * @response 409 scenario="already withdrawn" {"message": "You have already withdrawn from this course."}
     */
    public function withdraw(
        Request $request,
        Enrollment $enrollment,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        Notifier $notifier,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);
        $this->authorizeOwnEnrollment($enrollment, $student);

        if ($enrollment->course_status === CourseStatus::Withdrawn) {
            throw new ConflictHttpException('You have already withdrawn from this course.');
        }

        $offering = $this->offeringOf($enrollment);
        $semester = $this->semesterOf($offering, $calendar);

        if (! $semester->isInWithdrawalPeriod(now())) {
            throw new ConflictHttpException('The withdrawal period is not open for this course.');
        }

        // Withdrawal records W permanently — no credit-hour floor check (FR-REG-004).
        $enrollment->update(['course_status' => CourseStatus::Withdrawn]);

        $offerings = collect([$offering]);
        $this->attachOfferingRefs($offerings, $courses, $directory);
        $enrollment->setRelation('offering', $offering);

        $courseCode = $offering->getAttribute('resolved_course') instanceof CourseSummary
            ? $offering->getAttribute('resolved_course')->code
            : "course #{$offering->course_id}";

        $this->notifyEnrollmentWithdrawn(
            $notifier,
            $student->advisorId,
            $courseCode,
            $this->studentDisplayName($student, $directory),
        );

        return (new EnrollmentResource($enrollment))->response();
    }

    /**
     * Assert free add/drop is allowed for this Semester and Student right now: the Semester
     * must be in its add/drop window (else 409) and the Student must hold an approved Initial
     * request for it (else 403 — the Advisor is never bypassed on the initial schedule).
     */
    private function assertFreeChangeAllowed(SemesterSummary $semester, int $studentId): void
    {
        if (! $semester->isInAddDropPeriod(now())) {
            throw new ConflictHttpException('The add/drop period is not open for this course.');
        }

        abort_unless(
            RegistrationRequest::hasApprovedInitialFor($studentId, $semester->id),
            403,
            'Free add/drop needs an approved initial registration for this semester.',
        );
    }

    /** The acting Student owns this Enrollment, or a 404 (never leak another student's rows). */
    private function authorizeOwnEnrollment(Enrollment $enrollment, StudentSummary $student): void
    {
        abort_if((int) $enrollment->student_id !== $student->studentId, 404);
    }

    /** The Enrollment's Offering (with meetings), or a 404 when the reference dangles. */
    private function offeringOf(Enrollment $enrollment): CourseOffering
    {
        $offering = $enrollment->offering()->with('meetings')->first();
        abort_if($offering === null, 404, 'That course offering no longer exists.');

        return $offering;
    }

    /** The Offering's Semester through the Calendar facade, or a 404 when it dangles. */
    private function semesterOf(CourseOffering $offering, AcademicCalendar $calendar): SemesterSummary
    {
        $semester = $calendar->find((int) $offering->semester_id);
        abort_if($semester === null, 404, 'That semester no longer exists.');

        return $semester;
    }

    /**
     * Map the cart violations to a Laravel validation error bag, keyed by each violation's
     * stable code (the {@see RegistrationController} submission pattern).
     *
     * @param  list<CartViolation>  $violations
     * @return array<string, list<string>>
     */
    private function errorsFrom(array $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->code][] = $violation->message;
        }

        return $errors;
    }
}
