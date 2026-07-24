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
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
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
    use CountsEnrollments;
    use NotifiesRegistration;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;

    /**
     * List the acting Student's eligible courses for the open Semester.
     *
     * The courses the Student may register for right now, grouped client-side by their
     * Enrollment Classification (Preparatory / Core / Internal Elective / External
     * Elective / Research Course). Each line carries the Offering's instructor, meeting
     * times, seats remaining and the course's total CH. Courses in the Curriculum with
     * no Offering this semester are absent (ADR-0017); a course that classifies for no
     * class is omitted. When no registration window is open, `semester` is null and the
     * list is empty. Gated on `courses.register_self` (the acting Student, R02).
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
        $semester = $calendar->openForRegistration();

        // No open window — nothing to classify.
        if ($semester === null) {
            return $this->eligiblePayload(null, collect());
        }

        $offerings = $this->semesterOfferings($semester->id);
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
     * Turns the Student's cart into a Pending {@see RegistrationRequest} for the currently-
     * open Semester. Each line names an eligible Offering and a mode (Regular / Audit). The
     * whole cart is validated as a batch — every course must be eligible; the credit-hour
     * load must sit within the min/max window (the 6-hour floor is waived in the Student's
     * first semester); at most one External Elective and one Research Course; no duplicate
     * course; no time clash between chosen Offerings; and every capped Offering must have a
     * seat. An illegal cart is a 422 enumerating the offending reasons; a second Pending
     * request for the same Semester is a 409. Gated on `courses.register_self` (R02).
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

        $semester = $calendar->openForRegistration();
        if ($semester === null) {
            throw ValidationException::withMessages([
                'registration' => 'Registration is not open right now.',
            ]);
        }

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.offering_id' => ['required', 'integer'],
            'lines.*.mode' => ['required', Rule::enum(RegistrationMode::class)],
        ]);

        // At most one Pending request per (student, semester) — a Rejected one may be
        // replaced, but a second Pending is a conflict.
        $hasPending = RegistrationRequest::query()->pending()
            ->where('student_id', $student->studentId)
            ->where('semester_id', $semester->id)
            ->exists();

        if ($hasPending) {
            throw new ConflictHttpException('You already have a pending registration request for this semester.');
        }

        [$cartLines, $ineligible] = $this->resolveCart($student, $semester, $validated['lines'], $courses, $directory, $eligibility);

        $validator = new CartValidator(CreditHoursValidator::fromConfig());
        $violations = $validator->validate($cartLines, $this->exceptionsFor($student, $semester));

        $errors = $this->errorsFrom($violations, $ineligible);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $registration = DB::transaction(function () use ($student, $semester, $cartLines): RegistrationRequest {
            $registration = RegistrationRequest::create([
                'student_id' => $student->studentId,
                'semester_id' => $semester->id,
                'kind' => RegistrationKind::Initial,
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
        $semester = $calendar->openForRegistration();

        if ($semester === null) {
            return response()->json(['data' => ['semester' => null, 'requests' => []]]);
        }

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
     * Offering facades (ADR-0017). Attendance percentage and the period-gated action
     * buttons land in tickets 08/07; this ticket renders the read-only schedule. Gated on
     * `courses.register_self` (the acting Student, R02).
     *
     * @response 200 {"data": [{"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "enrollments": [{"id": 1, "offering_id": 1, "course_status": "Regular", "classification": "Core", "credit_hours": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}]}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function schedule(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        Directory $directory,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        $enrollments = Enrollment::query()
            ->where('student_id', $student->studentId)
            ->with('offering.meetings')
            ->orderBy('id')
            ->get();

        $offerings = $enrollments->pluck('offering')->filter()->values();
        $this->attachOfferingRefs($offerings, $courses, $directory);

        // Group by the Offering's Semester; render each group newest-first by start date.
        $groups = $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->offering !== null)
            ->groupBy(fn (Enrollment $enrollment): int => (int) $enrollment->offering->semester_id)
            ->map(function (Collection $group, int $semesterId) use ($calendar): ?array {
                $semester = $calendar->find($semesterId);
                if ($semester === null) {
                    return null; // a dangling Semester reference — omit the group
                }

                return [
                    'semester' => $this->semesterPayload($semester),
                    'starts_at' => $semester->startsAt,
                    'enrollments' => EnrollmentResource::collection($group->values())->resolve(),
                ];
            })
            ->filter()
            ->sortByDesc('starts_at')
            ->map(fn (array $group): array => ['semester' => $group['semester'], 'enrollments' => $group['enrollments']])
            ->values();

        return response()->json(['data' => $groups->all()]);
    }

    /**
     * The Student's display name for a notification line — resolved through the Identity
     * {@see Directory} (the Student anchor summary carries no name, ADR-0012). Falls back to
     * the University ID when the owning user cannot be resolved.
     */
    private function studentDisplayName(StudentSummary $student, Directory $directory): string
    {
        $users = $directory->findMany([$student->userId]);

        return isset($users[$student->userId]) ? $users[$student->userId]->name : $student->universityId;
    }

    /**
     * The acting Student's anchor, or a 403 when the acting user holds no Student record —
     * read through the Identity\Public {@see StudentDirectory} (ADR-0012).
     */
    private function actingStudent(Request $request, StudentDirectory $students): StudentSummary
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $student = $students->find($userId);
        abort_if($student === null, 403, 'You do not have a student record to register with.');

        return $student;
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
