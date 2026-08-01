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
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Http\Concerns\ComputesAttendance;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\DescribesStudentContext;
use Modules\Enrollment\Internal\Http\Concerns\MintsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ReadsExistingEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesStudent;
use Modules\Enrollment\Internal\Http\Concerns\ScopesToAdvisees;
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
 * Course registration (ADR-0017) — the surface where a cart becomes a
 * {@see RegistrationRequest}. Two audiences share it: a **Student (R02)** building their own
 * cart under `courses.register_self` (tickets 04–05), and **staff** composing one on a
 * Student's behalf under `courses.register_for_student` (ticket 09). The composing action is
 * the same either way; only who the cart is for — and whether it needs advisor approval —
 * differs, so the cart pipeline (resolve, validate, hydrate) is expressed once and reused.
 *
 * The eligible list is the Student's own Curriculum ∩ the currently-open Semester's
 * Offerings, plus other active graduate Programs' curriculum courses offered this semester
 * as External Electives — each line placed into its Enrollment Classification by the
 * {@see EligibilityResolver}. A submitted cart becomes a {@see RegistrationRequest} in
 * status Pending after passing the batch validators (credit-hour load, composition caps,
 * duplicates, clashes, capacity); the advisor decision and the Enrollment mint are ticket
 * 06. **Register-for-student** (ticket 09) reuses that pipeline against a *target* Student:
 * Student Affairs (or an administrator) compose a normal Pending request routed to the
 * Student's Advisor, while the Student's own Advisor composes a request that is **born
 * Approved** and mints immediately ({@see MintsEnrollments}) — the composer is the approver,
 * so making them approve their own paperwork is ceremony. Every cross-module fact is read
 * through a Public facade (no FK, ADR-0012): the Student through Identity\Public
 * {@see StudentDirectory}, the open Semester through {@see AcademicCalendar}, and each
 * Offering's Course/Instructor through {@see CourseCatalog}/{@see Directory}.
 */
class RegistrationController
{
    use ComputesAttendance;
    use CountsEnrollments;
    use DescribesStudentContext;
    use MintsEnrollments;
    use NotifiesRegistration;
    use ReadsExistingEnrollments;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;
    use ResolvesStudent;
    use ScopesToAdvisees;

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
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "courses": [{"offering_id": 1, "classification": "Core", "capacity": 30, "seats_remaining": 30, "total_ch": 3, "must_retake": false, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
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

        $mustRetake = $this->mustRetakeMarkersForStudent($student->studentId);
        $eligible = $this->eligibleCoursesFor($student, $target, $courses, $directory, $eligibility, $mustRetake['course_ids']);

        return $this->eligiblePayload($target->semester, $eligible);
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

        $validated = $request->validate($this->cartLineRules());

        // At most one Pending request per (student, semester) — a Rejected one may be
        // replaced, but a second Pending is a conflict. (An approved Initial does not
        // block a Pending LateAdd: the index is partial on Pending only.)
        if (RegistrationRequest::hasPendingFor($student->studentId, $semester->id)) {
            throw new ConflictHttpException('You already have a pending registration request for this semester.');
        }

        [$cartLines, $ineligible] = $this->resolveCart($student, $semester, $validated['lines'], $courses, $directory, $eligibility);

        // The student's existing record always matters for grade-gated rules (ticket 07):
        // prior attempts route Retake vs CGPA Improvement, an unresolved Incomplete blocks
        // re-registration, and a failed external elective may free the lifetime allowance.
        // For a LateAdd the current-semester schedule matters too (ceiling / duplicate /
        // clash); ExistingEnrollments carries both the historical and current-semester facts.
        $existing = $this->existingEnrollments($student->studentId, $semester->id);

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
     * @response 200 {"data": [{"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "current_period": "add_drop", "can_free_change": true, "can_withdraw": false, "addable_courses": [{"offering_id": 2, "classification": "Core", "capacity": 30, "seats_remaining": 28, "total_ch": 3, "must_retake": false, "course": {"id": 2, "code": "DEP502", "name_en": "Distributed Systems", "name_ar": "الأنظمة الموزعة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 3, "starts_at": "13:00", "ends_at": "15:00"}]}], "enrollments": [{"id": 1, "offering_id": 1, "course_status": "Regular", "classification": "Core", "credit_hours": 3, "must_retake": false, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}], "attendance": {"present": 8, "recorded": 10, "percentage": 80, "at_risk": false}}]}]}
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

        return response()->json([
            'data' => $this->scheduleGroups($student, withActions: true, calendar: $calendar, courses: $courses, directory: $directory, eligibility: $eligibility),
        ]);
    }

    /**
     * View any student's schedule (oversight).
     *
     * The administrative-override read of a specific Student's schedule (ticket 10, ADR-0015):
     * the student-self "My Schedule" surface is bound to `actingStudent`, so an administrator
     * — who holds no Student record — needs an id-addressable read to oversee "any student's
     * schedule" unscoped. It reuses the same builder and wire shape as My Schedule — each
     * Enrollment with its Course Status, classification, credit hours, meeting times and
     * derived attendance — but with the period-gated action affordances suppressed
     * (`can_free_change`/`can_withdraw` always false, `addable_courses` always empty): an
     * overseer views, never acts. The advisee-scope guard applies, so an **Advisor** reading
     * their own advisee is naturally permitted (an incidental start on user story 51 — the
     * full advisor monitoring UI is its own work); **Student Affairs** and an **administrator**
     * read any student. Unscoped reads are un-audited. Gated on `registration.approve`.
     *
     * @urlParam student_id integer required The target Student anchor's id. Example: 5
     *
     * @response 200 {"data": [{"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "current_period": "add_drop", "can_free_change": false, "can_withdraw": false, "addable_courses": [{"offering_id": 2, "classification": "Core", "capacity": 30, "seats_remaining": 28, "total_ch": 3, "must_retake": false, "course": {"id": 2, "code": "DEP502", "name_en": "Distributed Systems", "name_ar": "الأنظمة الموزعة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 3, "starts_at": "13:00", "ends_at": "15:00"}]}], "enrollments": [{"id": 1, "offering_id": 1, "course_status": "Regular", "classification": "Core", "credit_hours": 3, "must_retake": false, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}], "attendance": {"present": 8, "recorded": 10, "percentage": 80, "at_risk": false}}]}]}
     * @response 403 scenario="not this advisor's advisee" {"message": "This action is unauthorized."}
     * @response 404 scenario="no such student" {"message": "No such student."}
     */
    public function studentSchedule(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();

        $student = $students->findByStudentId((int) $request->route('student_id'));
        abort_if($student === null, 404, 'No such student.');

        // An Advisor may view only their own advisee; Student Affairs / an administrator any.
        $this->authorizeAdviseeScope($userId, $student->studentId, $directory, $students);

        return response()->json([
            'data' => $this->scheduleGroups($student, withActions: false, calendar: $calendar, courses: $courses, directory: $directory, eligibility: $eligibility),
        ]);
    }

    /**
     * The Student's minted Enrollments grouped by Semester (newest first by start date), each
     * carrying its derived attendance (ticket 08). When `$withActions` is true (the Student's
     * own My Schedule) each group also carries the period-gated change context so the SPA can
     * surface in-window actions; for an oversight read (`$withActions` false) those
     * affordances are suppressed — an overseer views, never acts — while `current_period`
     * stays informative. The wire shape is identical either way (Orval-stable).
     *
     * @return list<array<string, mixed>>
     */
    private function scheduleGroups(
        StudentSummary $student,
        bool $withActions,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
    ): array {
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
        $mustRetake = $this->mustRetakeMarkers($enrollments);
        $this->attachMustRetakeToEnrollments($enrollments, $mustRetake['enrollment_ids']);

        // Group by the Offering's Semester; render each group newest-first by start date,
        // enriched with the period-gated change context so the SPA can show in-window actions.
        return $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->offering !== null)
            ->groupBy(fn (Enrollment $enrollment): int => (int) $enrollment->offering->semester_id)
            ->map(function (Collection $group, int $semesterId) use ($calendar, $student, $courses, $directory, $eligibility, $withActions, $mustRetake): ?array {
                $semester = $calendar->find($semesterId);
                if ($semester === null) {
                    return null; // a dangling Semester reference — omit the group
                }

                $now = now();
                $canFreeChange = $withActions
                    && $semester->isInAddDropPeriod($now)
                    && RegistrationRequest::hasApprovedInitialFor($student->studentId, $semester->id);

                return [
                    'semester' => $this->semesterPayload($semester),
                    'starts_at' => $semester->startsAt,
                    'current_period' => $semester->currentPeriod($now),
                    'can_free_change' => $canFreeChange,
                    'can_withdraw' => $withActions && $semester->isInWithdrawalPeriod($now),
                    'addable_courses' => $canFreeChange
                        ? $this->addableCourses($student, $semester, $courses, $directory, $eligibility, $mustRetake['course_ids'])
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
            ->values()
            ->all();
    }

    /**
     * The students the composer may register on behalf of.
     *
     * The register-for-student picker (ticket 09): an Advisor sees their **live** advisees;
     * Student Affairs (or an administrator) sees **every** Student, unscoped — the same
     * visibility rule the advisor queue uses ({@see ScopesToAdvisees}). Each row carries the
     * Student's context (name, University ID, Program, degree, enrollment term) so the picker
     * is legible. Gated on `courses.register_for_student` (Advisor R03 / Student Affairs R05).
     *
     * @response 200 {"data": [{"student_id": 5, "name": "Sara Student", "university_id": "2026-MSC-CE-0001", "enrollment_term": "Fall 2026", "degree_level": "MSc", "current_load": null, "program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc"}}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function composableStudents(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        ProgramCatalog $programs,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();

        // Unscoped (SA / admin) → every Student; an Advisor → their live advisees only.
        $scope = $this->adviseeScope($userId, $directory, $students);
        $studentIds = $scope ?? $students->allStudentIds();

        $summaries = $students->findManyByStudentId($studentIds);
        if ($summaries === []) {
            return response()->json(['data' => []]);
        }

        [$users, $programSummaries] = $this->resolveStudentRefs($summaries, $directory, $programs);

        // Preserve the id order the scope returned (advisees / all, both ordered by id).
        $rows = [];
        foreach ($studentIds as $studentId) {
            $summary = $summaries[$studentId] ?? null;
            if ($summary === null) {
                continue;
            }
            $rows[] = $this->studentContext($summary, $programSummaries, $users, currentLoad: null);
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * A target student's eligible courses for the open Semester.
     *
     * The cart's course list on the register-for-student surface (ticket 09) — the same shape
     * as the self-serve eligible list, resolved for the target Student. An Advisor may read
     * only their own advisee's list ({@see ScopesToAdvisees}); Student Affairs / an
     * administrator any. Gated on `courses.register_for_student`.
     *
     * @urlParam student_id integer required The target Student anchor's id. Example: 5
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "courses": [{"offering_id": 1, "classification": "Core", "capacity": 30, "seats_remaining": 30, "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 scenario="no such student" {"message": "No such student to register for."}
     */
    public function eligibleCoursesForStudent(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        EligibilityResolver $eligibility,
    ): JsonResponse {
        $composerUserId = (int) $request->user()?->getAuthIdentifier();
        $student = $this->resolveTargetStudent($request, $composerUserId, $students, $directory);

        $target = $this->resolveRegistrationTarget($calendar);
        if ($target === null) {
            return $this->eligiblePayload(null, collect());
        }

        $mustRetake = $this->mustRetakeMarkersForStudent($student->studentId);
        $eligible = $this->eligibleCoursesFor($student, $target, $courses, $directory, $eligibility, $mustRetake['course_ids']);

        return $this->eligiblePayload($target->semester, $eligible);
    }

    /**
     * Compose and submit a registration cart on a student's behalf.
     *
     * Register-for-student (ticket 09). Runs the identical eligibility / credit-hour / caps /
     * clash / capacity validators the
     * Student's own submit runs, against the **target** Student, then routes by who is
     * composing (spec user stories 50, 53): the Student's own **Advisor** composes a request
     * that is **born Approved** — it mints Enrollments immediately in the same transaction and
     * notifies the Student (`registration.decided`), since the composer is the approver — while
     * **Student Affairs** (or an administrator) composes a normal **Pending** request routed to
     * the Student's live Advisor (`registration.submitted`), exactly as a self-submission. The
     * kind (Initial / LateAdd) is derived from the calendar, as on self-submit. A second
     * Pending request for the same Semester is a 409; an illegal cart is a 422 enumerating the
     * offending lines/reasons. Gated on `courses.register_for_student` (R03 / R05).
     *
     * @urlParam student_id integer required The target Student anchor's id. Example: 5
     *
     * @bodyParam lines object[] required The cart lines — one per chosen offering.
     * @bodyParam lines[].offering_id integer required An eligible Course Offering's id. Example: 1
     * @bodyParam lines[].mode string required How to take it — Regular or Audit. Example: Regular
     *
     * @response 201 {"data": {"id": 1, "semester_id": 1, "kind": "Initial", "status": "Approved", "advisor_note": null, "decided_at": "2026-08-02T09:00:00+00:00", "submitted_at": "2026-08-02T09:00:00+00:00", "lines": [{"id": 1, "offering_id": 1, "mode": "Regular", "decision": "approved", "derived_retake": false, "classification": "Core", "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 scenario="not this advisor's advisee" {"message": "This action is unauthorized."}
     * @response 409 scenario="a pending request already exists" {"message": "This student already has a pending registration request for this semester."}
     * @response 422 scenario="registration closed" {"message": "Registration is not open right now.", "errors": {"registration": ["Registration is not open right now."]}}
     */
    public function composeForStudent(
        Request $request,
        StudentDirectory $students,
        Directory $directory,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        EligibilityResolver $eligibility,
        Notifier $notifier,
    ): JsonResponse {
        $composerUserId = (int) $request->user()?->getAuthIdentifier();
        $student = $this->resolveTargetStudent($request, $composerUserId, $students, $directory);

        $target = $this->resolveRegistrationTarget($calendar);
        if ($target === null) {
            throw ValidationException::withMessages([
                'registration' => 'Registration is not open right now.',
            ]);
        }

        $semester = $target->semester;
        $kind = $target->kind;

        $validated = $request->validate($this->cartLineRules());

        // A staff compose never stacks on an existing Pending — the Advisor should decide it.
        if (RegistrationRequest::hasPendingFor($student->studentId, $semester->id)) {
            throw new ConflictHttpException('This student already has a pending registration request for this semester.');
        }

        [$cartLines, $ineligible] = $this->resolveCart($student, $semester, $validated['lines'], $courses, $directory, $eligibility);

        // The composer approves on the spot only when they are the Student's live Advisor
        // (spec story 50); SA / an administrator route a Pending request to the Advisor.
        $bornApproved = $composerUserId === $student->advisorId;

        // The target student's existing record always matters for the grade-gated rules, and
        // for born-Approved / LateAdd the current-semester schedule matters too. The single
        // ExistingEnrollments read carries both.
        $existing = $this->existingEnrollments($student->studentId, $semester->id);

        $validator = new CartValidator(CreditHoursValidator::fromConfig());
        $violations = $validator->validate($cartLines, $this->exceptionsFor($student, $semester), $existing);

        $mintPlan = [];
        if ($bornApproved) {
            [$mintPlan, $retakeViolations] = $this->buildMintPlan($cartLines, $existing);
            $violations = [...$violations, ...$retakeViolations];
        }

        $errors = $this->errorsFrom($violations, $ineligible);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $registration = DB::transaction(function () use ($student, $semester, $kind, $cartLines, $bornApproved, $composerUserId, $mintPlan): RegistrationRequest {
            $registration = RegistrationRequest::create([
                'student_id' => $student->studentId,
                'semester_id' => $semester->id,
                'kind' => $kind,
                'status' => $bornApproved ? RegistrationStatus::Approved : RegistrationStatus::Pending,
                'decided_at' => $bornApproved ? now() : null,
                'decided_by' => $bornApproved ? $composerUserId : null,
            ]);

            foreach ($cartLines as $line) {
                $registration->lines()->create([
                    'course_offering_id' => $line->offeringId,
                    'mode' => $line->mode,
                    'decision' => $bornApproved ? LineDecision::Approved : null,
                ]);
            }

            if ($bornApproved) {
                $this->mintEnrollments($student->studentId, $mintPlan);
            }

            return $registration;
        });

        $registration->load('lines.offering.meetings');

        // Side effect after commit: a born-Approved compose tells the Student their schedule
        // exists; a Pending compose tells the Student's live Advisor a request awaits review.
        if ($bornApproved) {
            $this->notifyRegistrationDecided($notifier, $student->userId, $registration, RegistrationStatus::Approved, count($mintPlan), 0);
        } else {
            $this->notifyRegistrationSubmitted(
                $notifier,
                $student->advisorId,
                $registration,
                $this->studentDisplayName($student, $directory),
                count($cartLines),
            );
        }

        $this->hydrateRequests(collect([$registration]), $student, $courses, $directory, $eligibility);

        return (new RegistrationRequestResource($registration))->response()->setStatusCode(201);
    }

    /**
     * Resolve the target Student for a register-for-student action from the route, 404ing an
     * unknown id, and assert the acting composer's advisee scope ({@see ScopesToAdvisees}): an
     * Advisor may act only for their own advisee, Student Affairs / an administrator for any.
     */
    private function resolveTargetStudent(Request $request, int $composerUserId, StudentDirectory $students, Directory $directory): StudentSummary
    {
        $student = $students->findByStudentId((int) $request->route('student_id'));
        abort_if($student === null, 404, 'No such student to register for.');

        $this->authorizeAdviseeScope($composerUserId, $student->studentId, $directory, $students);

        return $student;
    }

    /**
     * The cart-lines validation rules — one entry per chosen Offering with a mode — shared by
     * the Student's own submit and a staff compose-on-behalf (ticket 09), so both accept the
     * same cart shape.
     *
     * @return array<string, array<int, mixed>>
     */
    private function cartLineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.offering_id' => ['required', 'integer'],
            'lines.*.mode' => ['required', Rule::enum(RegistrationMode::class)],
        ];
    }

    /**
     * The Student's eligible Offerings for a submittable target (Initial / LateAdd), each with
     * its Enrollment Classification set — the shared body behind both the self-serve eligible
     * list and the register-for-student list (ticket 09). In the late-add window the cart only
     * adds, so Offerings the Student is already enrolled in this Semester are dropped (the mint
     * would reject a duplicate anyway).
     *
     * @param  list<int>  $mustRetakeCourseIds
     * @return Collection<int, CourseOffering>
     */
    private function eligibleCoursesFor(
        StudentSummary $student,
        RegistrationTarget $target,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
        array $mustRetakeCourseIds = [],
    ): Collection {
        $offerings = $this->semesterOfferings($target->semester->id);

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

        $this->attachMustRetakeToOfferings($eligible, $mustRetakeCourseIds);

        return $eligible;
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
     * @param  list<int>  $mustRetakeCourseIds
     * @return list<array<string, mixed>>
     */
    private function addableCourses(
        StudentSummary $student,
        SemesterSummary $semester,
        CourseCatalog $courses,
        Directory $directory,
        EligibilityResolver $eligibility,
        array $mustRetakeCourseIds = [],
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

        $this->attachMustRetakeToOfferings($addable, $mustRetakeCourseIds);

        return EligibleCourseResource::collection($addable)->resolve();
    }

    /**
     * The Student's current must-retake obligations, expressed both by Course id (for
     * eligible/addable offerings) and by the latest Enrollment id carrying that obligation
     * on the schedule. Derived only from Enrollment-owned facts: the classification snapshot
     * and the pushed `earned` bit (ADR-0022), never Grading reads.
     *
     * @param  Collection<int, Enrollment>  $enrollments  with `offering` loaded
     * @return array{course_ids: list<int>, enrollment_ids: list<int>}
     */
    private function mustRetakeMarkers(Collection $enrollments): array
    {
        $courseIds = [];
        $enrollmentIds = [];

        $byCourse = $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->offering !== null)
            ->groupBy(fn (Enrollment $enrollment): int => (int) $enrollment->offering->course_id);

        foreach ($byCourse as $courseId => $attempts) {
            /** @var Enrollment|null $latest */
            $latest = $attempts->last();
            if (! $latest instanceof Enrollment) {
                continue;
            }

            $mustRetake = false;
            foreach ($attempts as $attempt) {
                if (
                    $attempt->classification !== EnrollmentClassification::Preparatory
                    && $attempt->classification !== EnrollmentClassification::Core
                ) {
                    continue;
                }

                if ($attempt->earned === true) {
                    $mustRetake = false;

                    continue;
                }

                if ($attempt->earned === false) {
                    $mustRetake = true;
                }
            }

            if (! $mustRetake) {
                continue;
            }

            $courseIds[] = (int) $courseId;
            $enrollmentIds[] = (int) $latest->getKey();
        }

        return [
            'course_ids' => $courseIds,
            'enrollment_ids' => $enrollmentIds,
        ];
    }

    /**
     * The Student's must-retake markers from a fresh Enrollment read.
     *
     * @return array{course_ids: list<int>, enrollment_ids: list<int>}
     */
    private function mustRetakeMarkersForStudent(int $studentId): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $studentId)
            ->with('offering')
            ->orderBy('id')
            ->get();

        return $this->mustRetakeMarkers($enrollments);
    }

    /**
     * Stamp the must-retake flag onto schedule Enrollments before the resource serializes.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  list<int>  $mustRetakeEnrollmentIds
     */
    private function attachMustRetakeToEnrollments(Collection $enrollments, array $mustRetakeEnrollmentIds): void
    {
        $marked = array_fill_keys($mustRetakeEnrollmentIds, true);

        $enrollments->each(fn (Enrollment $enrollment) => $enrollment->setAttribute(
            'must_retake',
            isset($marked[(int) $enrollment->getKey()]),
        ));
    }

    /**
     * Stamp the must-retake flag onto eligible/addable Offerings by Course id.
     *
     * @param  Collection<int, CourseOffering>  $offerings
     * @param  list<int>  $mustRetakeCourseIds
     */
    private function attachMustRetakeToOfferings(Collection $offerings, array $mustRetakeCourseIds): void
    {
        $marked = array_fill_keys($mustRetakeCourseIds, true);

        $offerings->each(fn (CourseOffering $offering) => $offering->setAttribute(
            'must_retake',
            isset($marked[(int) $offering->course_id]),
        ));
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
