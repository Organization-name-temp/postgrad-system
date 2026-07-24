<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Http\Concerns\ComputesAttendance;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ReadsExistingEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesStudent;
use Modules\Enrollment\Internal\Http\Resources\EligibleCourseResource;
use Modules\Enrollment\Internal\Http\Resources\EnrollmentResource;
use Modules\Enrollment\Internal\Http\Resources\RegistrationRequestResource;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartValidator;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;
use Modules\Enrollment\Internal\Support\EligibilityResolver;
use Modules\Enrollment\Internal\Support\ExistingEnrollments;
use Modules\Enrollment\Internal\Support\RegistrationTarget;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Enrollment
 *
 * Student course registration (ADR-0017) — the self-serve surface a Student (R02) uses
 * under `courses.register_self`. Ticket 04 delivers the read-only eligibility list; ticket
 * 05 grows the cart submission and the request view here.
 *
 * The eligible list is the Student's own Curriculum ∩ the currently-open Semester's
 * Offerings, plus other active graduate Programs' curriculum courses offered this semester
 * as External Electives — each line placed into its Enrollment Classification by the
 * {@see EligibilityResolver}. A submitted cart becomes a {@see RegistrationRequest} in
 * status Pending after passing the batch validators (credit-hour load, composition caps,
 * duplicates, clashes, capacity); the advisor decision and the Enrollment mint are ticket
 * 06. Every cross-module fact is read through a Public facade (no FK, ADR-0012): the acting
 * Student through Identity\Public {@see StudentDirectory}, the open Semester through
 * {@see AcademicCalendar}, and each Offering's Course/Instructor through
 * {@see CourseCatalog}/{@see Directory}.
 */
class RegistrationController
{
    use ComputesAttendance;
    use CountsEnrollments;
    use NotifiesRegistration;
    use ReadsExistingEnrollments;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;
    use ResolvesStudent;

    /**
     * List the acting Student's eligible courses for the open Semester.
     *
     * The courses the Student may register for right now, grouped client-side by their
     * Enrollment Classification (Preparatory / Core / Internal Elective / External
     * Elective / Research Course). Each line carries the Offering's instructor, meeting
     * times, seats remaining and the course's total CH. Courses in the Curriculum with
     * no Offering this semester are absent (ADR-0017); a course that classifies for no
     * class is omitted. The list also serves the **late-add** window (ticket 07): when
     * registration has closed but the Semester is in its late-add period, the same list is
     * returned minus the courses already on the Student's schedule (a late-add cart only
     * adds). When neither a registration nor a late-add window is open, `semester` is null
     * and the list is empty. Gated on `courses.register_self` (the acting Student, R02).
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "courses": [{"offering_id": 1, "classification": "Core", "capacity": 30, "seats_remaining": 30, "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function eligibleCourses(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);
        $target = $this->resolveRegistrationTarget($calendar);

        // No submittable window (neither registration nor late-add) — nothing to classify.
        if ($target === null) {
            return $this->eligiblePayload(null, collect());
        }

        $semester = $target->semester;

        $offerings = $this->semesterOfferings($semester->id);

        // In the late-add window the cart adds to an already-approved schedule, so drop the
        // courses the Student is already enrolled in this Semester — only genuine additions
        // are offered (the mint would reject a duplicate anyway).
        if ($target->kind === RegistrationKind::LateAdd) {
            $enrolledOfferingIds = $this->enrolledOfferingIds($student->studentId);

            $offerings = $offerings
                ->reject(fn (CourseOffering $offering): bool => in_array((int) $offering->getKey(), $enrolledOfferingIds, true))
                ->values();
        }

        $this->attachOfferingRefs($offerings, $courses, $directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $eligibility->classify($student, $offerings);

        $eligible = $offerings->filter(function (CourseOffering $offering) use ($classifications): bool {
            $classification = $classifications[$offering->getKey()] ?? null;
            if ($classification === null) {
                return false;
            }
            $offering->setAttribute('classification', $classification);

            return true;
        })->values();

        return $this->eligiblePayload($semester, $eligible);
    }

    /**
     * Submit a registration cart.
     *
     * Turns the Student's cart into a Pending {@see RegistrationRequest}. Its kind is derived
     * from the calendar: `Initial` while the registration window is open, or `LateAdd` when
     * that window has closed and the Semester is in its late-add period (ticket 07) — the
     * add-only week-3 request that still flows through the advisor. Each line names an
     * eligible Offering and a mode (Regular / Audit). The whole cart is validated as a batch
     * — every course must be eligible; the credit-hour load must sit within the min/max
     * window (the 6-hour floor is waived in the Student's first semester); at most one
     * External Elective and one Research Course; no duplicate course; no time clash between
     * chosen Offerings; and every capped Offering must have a seat. A late-add cart is
     * additionally measured against the Student's already-approved schedule. An illegal cart
     * is a 422 enumerating the offending reasons; a second Pending request for the same
     * Semester is a 409. Gated on `courses.register_self` (R02).
     *
     * @bodyParam lines object[] required The cart lines — one per chosen offering.
     * @bodyParam lines[].offering_id integer required An eligible Course Offering's id. Example: 1
     * @bodyParam lines[].mode string required How to take it — Regular or Audit. Example: Regular
     *
     * @response 201 {"data": {"id": 1, "semester_id": 1, "kind": "Initial", "status": "Pending", "advisor_note": null, "submitted_at": "2026-08-02T09:00:00+00:00", "lines": [{"offering_id": 1, "mode": "Regular", "classification": "Core", "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     * @response 409 scenario="a pending request already exists" {"message": "You already have a pending registration request for this semester."}
     * @response 422 scenario="registration closed" {"message": "Registration is not open right now.", "errors": {"registration": ["Registration is not open right now."]}}
     * @response 422 scenario="credit-hour floor" {"message": "Your registration totals 3 credit hours, below the 6-hour minimum.", "errors": {"below_min_credit_hours": ["Your registration totals 3 credit hours, below the 6-hour minimum."]}}
     */
    public function submit(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
        Notifier $notifier,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        $target = $this->resolveRegistrationTarget($calendar);
        if ($target === null) {
            throw ValidationException::withMessages([
                'registration' => 'Registration is not open right now.',
            ]);
        }

        $semester = $target->semester;
        $kind = $target->kind;

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.offering_id' => ['required', 'integer'],
            'lines.*.mode' => ['required', Rule::enum(RegistrationMode::class)],
        ]);

        // At most one Pending request per (student, semester) — a Rejected one may be
        // replaced, but a second Pending is a conflict. (An approved Initial does not
        // block a Pending LateAdd: the index is partial on Pending only.)
        $hasPending = RegistrationRequest::query()->pending()
            ->where('student_id', $student->studentId)
            ->where('semester_id', $semester->id)
            ->exists();

        if ($hasPending) {
            throw new ConflictHttpException('You already have a pending registration request for this semester.');
        }

        [$cartLines, $ineligible] = $this->resolveCart($student, $semester, $validated['lines'], $courses, $directory, $eligibility);

        // A late-add cart is measured against the already-approved schedule (ceiling, caps,
        // duplicate, clash all count the record); an initial cart has no record yet.
        $existing = $kind === RegistrationKind::LateAdd
            ? $this->existingEnrollments($student->studentId, $semester->id)
            : ExistingEnrollments::none();

        $validator = new CartValidator(CreditHoursValidator::fromConfig());
        $violations = $validator->validate($cartLines, $this->exceptionsFor($student, $semester), $existing);

        $errors = $this->errorsFrom($violations, $ineligible);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $registration = DB::transaction(function () use ($student, $semester, $kind, $cartLines): RegistrationRequest {
            $registration = RegistrationRequest::create([
                'student_id' => $student->studentId,
                'semester_id' => $semester->id,
                'kind' => $kind,
                'status' => RegistrationStatus::Pending,
            ]);

            foreach ($cartLines as $line) {
                $registration->lines()->create([
                    'course_offering_id' => $line->offeringId,
                    'mode' => $line->mode,
                ]);
            }

            return $registration;
        });

        // Side effect after the transaction commits: tell the Student's live Advisor a
        // registration awaits their review (ticket 06). The advisee↔advisor link resolves
        // live, so the notification follows the Student's current advisor.
        $this->notifyRegistrationSubmitted(
            $notifier,
            $student->advisorId,
            $registration,
            $this->studentDisplayName($student, $directory),
            count($cartLines),
        );

        $registration->load('lines.offering.meetings');
        $this->hydrateRequests(collect([$registration]), $student, $courses, $directory, $eligibility);

        return (new RegistrationRequestResource($registration))->response()->setStatusCode(201);
    }

    /**
     * The acting Student's registration requests for the open Semester.
     *
     * Every request the Student has submitted for the currently-open Semester, newest
     * first, each with its status (Pending / Approved / Rejected) and per-line detail — the
     * request view (user story 30). A Rejected request stays visible so the Student sees it
     * alongside any fresh Pending cart. When no window is open, `semester` is null and the
     * list is empty. Gated on `courses.register_self` (R02).
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "requests": [{"id": 1, "semester_id": 1, "kind": "Initial", "status": "Pending", "advisor_note": null, "submitted_at": "2026-08-02T09:00:00+00:00", "lines": [{"offering_id": 1, "mode": "Regular", "classification": "Core", "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}]}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function requests(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);
        $target = $this->resolveRegistrationTarget($calendar);

        if ($target === null) {
            return response()->json(['data' => ['semester' => null, 'requests' => []]]);
        }

        $semester = $target->semester;

        $requests = RegistrationRequest::query()
            ->where('student_id', $student->studentId)
            ->where('semester_id', $semester->id)
            ->with('lines.offering.meetings')
            ->orderByDesc('id')
            ->get();

        $this->hydrateRequests($requests, $student, $courses, $directory, $eligibility);

        return response()->json([
            'data' => [
                'semester' => $this->semesterPayload($semester),
                'requests' => RegistrationRequestResource::collection($requests)->resolve(),
            ],
        ]);
    }

    /**
     * The acting Student's schedule — their minted Enrollments, grouped by Semester.
     *
     * The Student's academic present (user story 32, FR-REG-005): every Enrollment the
     * advisor minted, grouped by Semester (most recent first), each carrying its Course
     * Status, Enrollment Classification, registration-time credit hours, and the live
     * display facts — course, instructor, weekly meeting times — read through the Catalog/
     * Offering facades (ADR-0017).
     *
     * Each group also carries the **period-gated change context** (ticket 07, FR-REG-004),
     * so the SPA can surface the right in-window actions while the server stays the sole
     * authority: `current_period` (the derived phase — upcoming / registration / add_drop /
     * late_add / withdrawal / closed), `can_free_change` (in the add/drop window *and* the
     * Student has an approved Initial request — free add/drop is live), `can_withdraw` (in
     * the withdrawal window), and — only when free change is live — `addable_courses`, the
     * eligible Offerings not yet on the schedule and not External Electives (those need a
     * request). Each enrollment also carries its derived `attendance` (ticket 08) — present/
     * recorded counts, the display percentage, and the audit-at-risk flag. Gated on
     * `courses.register_self` (the acting Student, R02).
     *
     * @response 200 {"data": [{"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "current_period": "add_drop", "can_free_change": true, "can_withdraw": false, "addable_courses": [{"offering_id": 2, "classification": "Core", "capacity": 30, "seats_remaining": 28, "total_ch": 3, "course": {"id": 2, "code": "DEP502", "name_en": "Distributed Systems", "name_ar": "الأنظمة الموزعة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 3, "starts_at": "13:00", "ends_at": "15:00"}]}], "enrollments": [{"id": 1, "offering_id": 1, "course_status": "Regular", "classification": "Core", "credit_hours": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}], "attendance": {"present": 8, "recorded": 10, "percentage": 80, "at_risk": false}}]}]}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function schedule(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        $enrollments = Enrollment::query()
            ->where('student_id', $student->studentId)
            ->with('offering.meetings')
            ->orderBy('id')
            ->get();

        $offerings = $enrollments->pluck('offering')->filter()->values();
        $this->attachOfferingRefs($offerings, $courses, $directory);

        // Attach each Enrollment's derived attendance (ticket 08) so My Schedule shows the
        // percentage and audit-at-risk chip — one batched tally read across the schedule.
        $this->attachAttendance($enrollments);

        // Group by the Offering's Semester; render each group newest-first by start date,
        // enriched with the period-gated change context so the SPA can show in-window actions.
        $groups = $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->offering !== null)
            ->groupBy(fn (Enrollment $enrollment): int => (int) $enrollment->offering->semester_id)
            ->map(function (Collection $group, int $semesterId) use ($calendar, $student, $courses, $directory, $eligibility): ?array {
                $semester = $calendar->find($semesterId);
                if ($semester === null) {
                    return null; // a dangling Semester reference — omit the group
                }

                $now = now();
                $canFreeChange = $semester->isInAddDropPeriod($now)
                    && RegistrationRequest::hasApprovedInitialFor($student->studentId, $semester->id);

                return [
                    'semester' => $this->semesterPayload($semester),
                    'starts_at' => $semester->startsAt,
                    'current_period' => $semester->currentPeriod($now),
                    'can_free_change' => $canFreeChange,
                    'can_withdraw' => $semester->isInWithdrawalPeriod($now),
                    'addable_courses' => $canFreeChange
                        ? $this->addableCourses($student, $semester, $courses, $directory, $eligibility)
                        : [],
                    'enrollments' => EnrollmentResource::collection($group->values())->resolve(),
                ];
            })
            ->filter()
            ->sortByDesc('starts_at')
            ->map(fn (array $group): array => [
                'semester' => $group['semester'],
                'current_period' => $group['current_period'],
                'can_free_change' => $group['can_free_change'],
                'can_withdraw' => $group['can_withdraw'],
                'addable_courses' => $group['addable_courses'],
                'enrollments' => $group['enrollments'],
            ])
            ->values();

        return response()->json(['data' => $groups->all()]);
    }

    /**
     * The Semester's Offerings (with meetings), ordered for a stable list.
     *
     * @return Collection<int, CourseOffering>
     */
    private function semesterOfferings(int $semesterId): Collection
    {
        return CourseOffering::query()
            ->where('semester_id', $semesterId)
            ->with('meetings')
            ->orderBy('id')
            ->get();
    }

    /**
     * The Semester the acting Student may submit a cart for right now, and the kind of
     * request it would be: the Semester whose registration window is open (an `Initial`
     * cart), or — when none is — the running Semester inside its late-add window (an
     * add-only `LateAdd` cart, ticket 07, FR-REG-004). Null when neither applies. Every
     * period question reads from Calendar (ADR-0018), so time alone moves the answer.
     */
    private function resolveRegistrationTarget(AcademicCalendar $calendar): ?RegistrationTarget
    {
        $open = $calendar->openForRegistration();
        if ($open !== null) {
            return new RegistrationTarget($open, RegistrationKind::Initial);
        }

        $running = $calendar->running();
        if ($running !== null && $running->isInLateAddPeriod(now())) {
            return new RegistrationTarget($running, RegistrationKind::LateAdd);
        }

        return null;
    }

    /**
     * The Offering ids the Student already has an Enrollment for (any status) — the "leave
     * out what's already on the schedule" filter shared by the late-add eligible list and
     * the free-add list (ticket 07).
     *
     * @return list<int>
     */
    private function enrolledOfferingIds(int $studentId): array
    {
        return Enrollment::query()
            ->where('student_id', $studentId)
            ->pluck('course_offering_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The courses the Student may **free-add** to this Semester right now (ticket 07): the
     * eligible Offerings minus those already on their schedule and minus External Electives
     * (an external elective is only legal inside a request, BR-24). The same
     * {@see EligibleCourseResource} shape the registration list uses, so the SPA renders it
     * with the existing row component.
     *
     * @return list<array<string, mixed>>
     */
    private function addableCourses(
        StudentSummary $student,
        SemesterSummary $semester,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): array {
        $offerings = $this->semesterOfferings($semester->id);
        $this->attachOfferingRefs($offerings, $courses, $directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $eligibility->classify($student, $offerings);

        $enrolledOfferingIds = $this->enrolledOfferingIds($student->studentId);

        $addable = $offerings->filter(function (CourseOffering $offering) use ($classifications, $enrolledOfferingIds): bool {
            $classification = $classifications[$offering->getKey()] ?? null;

            // Not eligible, an external elective (needs a request), or already on the schedule.
            if ($classification === null || $classification === EnrollmentClassification::ExternalElective) {
                return false;
            }
            if (in_array((int) $offering->getKey(), $enrolledOfferingIds, true)) {
                return false;
            }

            $offering->setAttribute('classification', $classification);

            return true;
        })->values();

        return EligibleCourseResource::collection($addable)->resolve();
    }

    /**
     * Resolve the submitted cart lines against the open Semester's Offerings: build a
     * {@see CartLine} for each eligible line, and collect the submitted-line indexes whose
     * Offering is not in this Semester or does not classify for the Student (ineligible).
     *
     * @param  array<int, array{offering_id: int, mode: string}>  $lines
     * @return array{0: list<CartLine>, 1: array<int, int>} [cart lines, index => offering id]
     */
    private function resolveCart(
        StudentSummary $student,
        SemesterSummary $semester,
        array $lines,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): array {
        $offeringIds = collect($lines)->pluck('offering_id')->map(fn ($id): int => (int) $id)->unique()->all();

        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->whereIn('id', $offeringIds)
            ->with('meetings')
            ->get()
            ->keyBy('id');

        $this->attachOfferingRefs($offerings, $courses, $directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $eligibility->classify($student, $offerings);

        $cartLines = [];
        $ineligible = [];

        foreach ($lines as $index => $line) {
            $offeringId = (int) $line['offering_id'];
            $offering = $offerings->get($offeringId);
            $classification = $classifications[$offeringId] ?? null;
            $course = $offering?->getAttribute('resolved_course');

            if ($offering === null || $classification === null || ! $course instanceof CourseSummary) {
                $ineligible[$index] = $offeringId;

                continue;
            }

            $cartLines[] = new CartLine(
                offeringId: $offeringId,
                courseId: (int) $offering->course_id,
                courseCode: $course->code,
                mode: RegistrationMode::from($line['mode']),
                totalCh: $course->totalCh,
                classification: $classification,
                seatsRemaining: $offering->seatsRemaining(),
                meetings: $this->meetingSlots($offering),
            );
        }

        return [$cartLines, $ineligible];
    }

    /**
     * Map the cart violations and ineligible lines to a Laravel validation error bag:
     * cart violations keyed by their stable code (with a human message), and each
     * ineligible submitted line keyed by its `lines.{index}.offering_id`.
     *
     * @param  list<CartViolation>  $violations
     * @param  array<int, int>  $ineligible  submitted-line index => offering id
     * @return array<string, list<string>>
     */
    private function errorsFrom(array $violations, array $ineligible): array
    {
        $errors = [];

        foreach ($ineligible as $index => $offeringId) {
            $errors["lines.{$index}.offering_id"][] = 'This course is not available for you to register this semester.';
        }

        foreach ($violations as $violation) {
            $errors[$violation->code][] = $violation->message;
        }

        return $errors;
    }

    /**
     * Attach each request's lines' resolved Course/Instructor and derived classification
     * onto the lines' Offerings, so {@see RegistrationRequestResource} renders live display
     * facts (ADR-0012). One batched facade read across every line's Offering.
     *
     * @param  Collection<int, RegistrationRequest>  $requests  with `lines.offering.meetings` loaded
     */
    private function hydrateRequests(
        Collection $requests,
        StudentSummary $student,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): void {
        /** @var Collection<int, CourseOffering> $offerings */
        $offerings = $requests->pluck('lines')->collapse()->pluck('offering')->filter()->values();
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
     * The `{ data: { semester, courses } }` envelope for the eligible list.
     *
     * @param  Collection<int, CourseOffering>  $eligible
     */
    private function eligiblePayload(?SemesterSummary $semester, Collection $eligible): JsonResponse
    {
        return response()->json([
            'data' => [
                'semester' => $semester === null ? null : $this->semesterPayload($semester),
                'courses' => EligibleCourseResource::collection($eligible)->resolve(),
            ],
        ]);
    }

    /**
     * The open Semester's display facts and key dates, mirroring Calendar's own
     * SemesterResource shape (built here from the {@see SemesterSummary} facade DTO,
     * since Calendar's resource is Internal to that module — ADR-0012).
     *
     * @return array<string, mixed>
     */
    private function semesterPayload(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
            'starts_at' => $semester->startsAt->toIso8601String(),
            'ends_at' => $semester->endsAt->toIso8601String(),
            'registration_opens_at' => $semester->registrationOpensAt->toIso8601String(),
            'registration_closes_at' => $semester->registrationClosesAt->toIso8601String(),
            'add_drop_ends_at' => $semester->addDropEndsAt->toIso8601String(),
            'late_add_ends_at' => $semester->lateAddEndsAt->toIso8601String(),
            'withdrawal_deadline_at' => $semester->withdrawalDeadlineAt->toIso8601String(),
            'is_registration_open' => $semester->isRegistrationOpen(now()),
        ];
    }
}
