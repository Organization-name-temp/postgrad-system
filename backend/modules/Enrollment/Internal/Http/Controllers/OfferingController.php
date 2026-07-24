<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Resources\CourseOfferingResource;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Support\MeetingTime;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Enrollment
 *
 * Course Offering management (ADR-0017, ticket 03) — "what actually runs this
 * semester." An administrator holding `calendar.manage` (Student Affairs / Dean;
 * SysAdmin inherits) populates a Semester with Offerings: one Catalog Course taught in
 * that Semester, carrying an assigned Instructor, an optional seat capacity, and one or
 * more weekly meeting times. This is the record Phase-4 grading's Instructor→Course
 * link is born from.
 *
 * The Semester, Course and Instructor are referenced by opaque id (no cross-module
 * foreign key, ADR-0012); each is validated through its module's Public facade — the
 * Course through {@see CourseCatalog}, the Instructor (a faculty R04 User) through the
 * Identity\Public {@see Directory}, the Semester through
 * {@see AcademicCalendar}. One Course runs at most once per
 * Semester (unique), an Offering's own meetings may not overlap each other (the
 * interval-overlap seam), and an Offering is deletable only while nothing references it.
 */
class OfferingController
{
    use ResolvesOfferingRefs;

    /**
     * The role an Offering's instructor must hold — Instructor (R04), E-JUST's faculty
     * teaching-staff role. Kept in one place so the picker and the create/update
     * validation agree on who may be assigned to teach.
     */
    private const Role INSTRUCTOR_ROLE = Role::Instructor;

    /**
     * List a Semester's Course Offerings.
     *
     * Every Offering that runs in the given Semester, each with its resolved Course and
     * Instructor summaries and its weekly meetings. Gated on `calendar.manage`.
     *
     * @urlParam semester_id integer required The ID of the semester. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "semester_id": 1, "course_id": 1, "instructor_id": 4, "capacity": 30, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"id": 1, "weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No semester found for the given id."}
     */
    public function index(
        Request $request,
        CourseCatalog $courses,
        Directory $directory,
        AcademicCalendar $calendar,
    ): AnonymousResourceCollection {
        $semesterId = $this->semesterIdFromRoute($request);
        $this->assertSemesterExists($semesterId, $calendar);

        $offerings = CourseOffering::query()
            ->where('semester_id', $semesterId)
            ->with('meetings')
            ->orderBy('id')
            ->get();

        $this->attachOfferingRefs($offerings, $courses, $directory);

        return CourseOfferingResource::collection($offerings);
    }

    /**
     * Show a Course Offering.
     *
     * @urlParam offering_id integer required The ID of the offering. Example: 1
     *
     * @response 200 {"data": {"id": 1, "semester_id": 1, "course_id": 1, "instructor_id": 4, "capacity": 30, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"id": 1, "weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function show(
        CourseOffering $offering,
        CourseCatalog $courses,
        Directory $directory,
    ): CourseOfferingResource {
        $offering->load('meetings');
        $this->attachOfferingRefs(collect([$offering]), $courses, $directory);

        return new CourseOfferingResource($offering);
    }

    /**
     * Create a Course Offering in a Semester.
     *
     * Records one Catalog Course as taught in the Semester: an Instructor, an optional
     * seat capacity, and one or more weekly meeting times. The Course must exist and not
     * already be offered in this Semester; the Instructor must be a faculty (R04) User;
     * the meetings may not overlap one another. Gated on `calendar.manage`.
     *
     * @urlParam semester_id integer required The ID of the semester. Example: 1
     *
     * @bodyParam course_id integer required The Catalog Course to offer. Example: 1
     * @bodyParam instructor_id integer required A faculty (Instructor) User to teach it. Example: 4
     * @bodyParam capacity integer The seat capacity; omit for uncapped. Example: 30
     * @bodyParam meetings object[] required One or more weekly meeting slots.
     * @bodyParam meetings[].weekday integer required Day of week, 0 (Sunday) to 6 (Saturday). Example: 1
     * @bodyParam meetings[].starts_at string required Start time, 24h HH:MM. Example: 09:00
     * @bodyParam meetings[].ends_at string required End time, 24h HH:MM, after the start. Example: 11:00
     *
     * @response 201 {"data": {"id": 1, "semester_id": 1, "course_id": 1, "instructor_id": 4, "capacity": 30, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"id": 1, "weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No semester found for the given id."}
     * @response 422 scenario="course already offered" {"message": "This course is already offered in this semester.", "errors": {"course_id": ["This course is already offered in this semester."]}}
     * @response 422 scenario="meetings overlap" {"message": "These meeting times overlap each other.", "errors": {"meetings": ["These meeting times overlap each other."]}}
     */
    public function store(
        Request $request,
        CourseCatalog $courses,
        Directory $directory,
        AcademicCalendar $calendar,
    ): JsonResponse {
        $semesterId = $this->semesterIdFromRoute($request);
        $this->assertSemesterExists($semesterId, $calendar);

        $validated = $this->validated($request, $directory, $semesterId, includeCourse: true);
        $this->assertCourseExists((int) $validated['course_id'], $courses);
        $this->assertMeetings($validated['meetings']);

        $offering = DB::transaction(function () use ($semesterId, $validated): CourseOffering {
            $offering = CourseOffering::create([
                'semester_id' => $semesterId,
                'course_id' => $validated['course_id'],
                'instructor_id' => $validated['instructor_id'],
                'capacity' => $validated['capacity'] ?? null,
            ]);

            $this->syncMeetings($offering, $validated['meetings']);

            return $offering;
        });

        $offering->load('meetings');
        $this->attachOfferingRefs(collect([$offering]), $courses, $directory);

        return (new CourseOfferingResource($offering))->response()->setStatusCode(201);
    }

    /**
     * Update a Course Offering.
     *
     * Changes the Instructor, capacity, and weekly meetings. The Semester and Course an
     * Offering runs are its identity and are fixed at creation. Same instructor and
     * meeting invariants as creation. Gated on `calendar.manage`.
     *
     * @urlParam offering_id integer required The ID of the offering. Example: 1
     *
     * @bodyParam instructor_id integer required A faculty (Instructor) User to teach it. Example: 4
     * @bodyParam capacity integer The seat capacity; omit for uncapped. Example: 25
     * @bodyParam meetings object[] required One or more weekly meeting slots.
     * @bodyParam meetings[].weekday integer required Day of week, 0 (Sunday) to 6 (Saturday). Example: 2
     * @bodyParam meetings[].starts_at string required Start time, 24h HH:MM. Example: 13:00
     * @bodyParam meetings[].ends_at string required End time, 24h HH:MM, after the start. Example: 15:00
     *
     * @response 200 {"data": {"id": 1, "semester_id": 1, "course_id": 1, "instructor_id": 4, "capacity": 25, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"id": 2, "weekday": 2, "starts_at": "13:00", "ends_at": "15:00"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 422 scenario="meetings overlap" {"message": "These meeting times overlap each other.", "errors": {"meetings": ["These meeting times overlap each other."]}}
     */
    public function update(
        Request $request,
        CourseOffering $offering,
        CourseCatalog $courses,
        Directory $directory,
    ): CourseOfferingResource {
        $validated = $this->validated($request, $directory, $offering->semester_id, includeCourse: false);
        $this->assertMeetings($validated['meetings']);

        DB::transaction(function () use ($offering, $validated): void {
            $offering->update([
                'instructor_id' => $validated['instructor_id'],
                'capacity' => $validated['capacity'] ?? null,
            ]);

            $offering->meetings()->delete();
            $this->syncMeetings($offering, $validated['meetings']);
        });

        $offering->load('meetings');
        $this->attachOfferingRefs(collect([$offering]), $courses, $directory);

        return new CourseOfferingResource($offering);
    }

    /**
     * Delete a Course Offering.
     *
     * Removes an Offering and its meeting rows. Allowed only while nothing references
     * it — once an Enrollment is minted against it or a Class Session is generated from
     * it (later Phase-3 tickets), the register and roster it anchors must survive, so a
     * referenced Offering is refused with a 409. Gated on `calendar.manage`.
     *
     * @urlParam offering_id integer required The ID of the offering. Example: 1
     *
     * @response 204
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 {"message": "This offering has enrollments or sessions and cannot be deleted."}
     */
    public function destroy(CourseOffering $offering): JsonResponse
    {
        if ($offering->isReferenced()) {
            throw new ConflictHttpException('This offering has enrollments or sessions and cannot be deleted.');
        }

        $offering->delete();

        return response()->json(status: 204);
    }

    /**
     * List assignable instructors.
     *
     * The faculty Users (holding the Instructor role, R04) an Offering may be assigned
     * to — the source for the offering form's instructor picker (the Phase-2
     * interviewer-list precedent, via Identity\Public). Gated on `calendar.manage`.
     *
     * @response 200 {"data": [{"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function instructors(Directory $directory): JsonResponse
    {
        $instructors = array_map(
            static fn (UserSummary $summary): array => $summary->toArray(),
            $directory->usersByRole(self::INSTRUCTOR_ROLE),
        );

        return response()->json(['data' => $instructors]);
    }

    /**
     * Base validation rules. On create the Course id is required and must not already be
     * offered in this Semester; on update the Course (and Semester) are fixed, so it is
     * omitted. The instructor must hold the Instructor role — read from the
     * Identity\Public directory, so a non-faculty id is a 422 rather than an Offering
     * taught by no one who can grade it.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Directory $directory, int $semesterId, bool $includeCourse): array
    {
        $instructorIds = array_map(
            static fn (UserSummary $summary): int => $summary->id,
            $directory->usersByRole(self::INSTRUCTOR_ROLE),
        );

        $rules = [
            'instructor_id' => ['required', 'integer', Rule::in($instructorIds)],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'meetings' => ['required', 'array', 'min:1'],
            'meetings.*.weekday' => ['required', 'integer', 'between:0,6'],
            'meetings.*.starts_at' => ['required', 'date_format:H:i'],
            'meetings.*.ends_at' => ['required', 'date_format:H:i'],
        ];

        if ($includeCourse) {
            $rules['course_id'] = [
                'required',
                'integer',
                // One Course runs at most once per Semester (relaxable to sections later,
                // ADR-0017). Existence in the Catalog is checked separately via the facade.
                Rule::unique('course_offerings', 'course_id')->where('semester_id', $semesterId),
            ];
        }

        return $request->validate($rules, [
            'instructor_id.in' => 'The selected instructor is not a faculty member.',
            'course_id.unique' => 'This course is already offered in this semester.',
        ]);
    }

    /**
     * Resolve the Semester id from the nested route parameter.
     */
    private function semesterIdFromRoute(Request $request): int
    {
        return (int) $request->route('semester_id');
    }

    /**
     * 404 when the referenced Semester does not exist — read through the Calendar\Public
     * facade, the sanctioned cross-module seam (ADR-0012/0018).
     */
    private function assertSemesterExists(int $semesterId, AcademicCalendar $calendar): void
    {
        abort_if(
            $calendar->find($semesterId) === null,
            404,
            'No semester found for the given id.',
        );
    }

    /**
     * 422 when the chosen Course does not exist in the Catalog — resolved through the
     * Catalog\Public facade (ADR-0012). A dangling course id must never be offered.
     */
    private function assertCourseExists(int $courseId, CourseCatalog $courses): void
    {
        if ($courses->find($courseId) === null) {
            throw ValidationException::withMessages([
                'course_id' => 'The selected course does not exist.',
            ]);
        }
    }

    /**
     * Enforce the meeting invariants through the pure {@see MeetingTime} seam: each slot
     * must end after it starts, and no two slots may overlap (a course cannot be
     * scheduled against itself, ADR-0017). Raised as a 422 keyed to `meetings`.
     *
     * @param  array<int, array{weekday: int, starts_at: string, ends_at: string}>  $meetings
     */
    private function assertMeetings(array $meetings): void
    {
        $slots = array_map(
            static fn (array $meeting): MeetingTime => MeetingTime::fromClock(
                $meeting['weekday'],
                $meeting['starts_at'],
                $meeting['ends_at'],
            ),
            $meetings,
        );

        foreach ($slots as $slot) {
            if (! $slot->isWellFormed()) {
                throw ValidationException::withMessages([
                    'meetings' => 'A meeting must end after it starts.',
                ]);
            }
        }

        if (MeetingTime::hasOverlap($slots)) {
            throw ValidationException::withMessages([
                'meetings' => 'These meeting times overlap each other.',
            ]);
        }
    }

    /**
     * Replace the Offering's meeting rows with the validated set.
     *
     * @param  array<int, array{weekday: int, starts_at: string, ends_at: string}>  $meetings
     */
    private function syncMeetings(CourseOffering $offering, array $meetings): void
    {
        foreach ($meetings as $meeting) {
            $offering->meetings()->create([
                'weekday' => $meeting['weekday'],
                'starts_at' => $meeting['starts_at'],
                'ends_at' => $meeting['ends_at'],
            ]);
        }
    }
}
