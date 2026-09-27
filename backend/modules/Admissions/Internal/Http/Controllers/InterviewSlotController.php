<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Http\Concerns\ResolvesInterviewers;
use Modules\Admissions\Internal\Http\Resources\InterviewSlotResource;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Admissions\Internal\Support\InterviewSlotCollection;
use Modules\Admissions\Internal\Support\InterviewSlotExport;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\UserSummary;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * Interview-slot pool management (ticket 09) — Student Affairs sets up the pool of
 * appointments approved applicants can be scheduled into. Each slot is a single
 * appointment (capacity one) with a time, duration, assigned interviewer, and mode.
 * Gated on `interview.schedule` (Student Affairs / Dean).
 *
 * The interviewer is a **faculty User** — one holding the Instructor role (R04), the
 * teaching-staff role. The eligible set is read through the Identity\Public directory
 * facade ({@see Directory}); the chosen interviewer is validated against it at create
 * and update, and rendered for display through the same facade (ADR-0012 — no FK, no
 * Eloquent relation across the seam).
 *
 * A slot may be edited or deleted only while it is **open**: once an interview is
 * booked into it, changing its time/interviewer would desync the applicant, so both
 * are refused with a 409.
 */
class InterviewSlotController
{
    use ResolvesInterviewers;

    /**
     * The role a slot's interviewer must hold — Instructor (R04), E-JUST's faculty
     * teaching-staff role. Kept in one place so the picker and the create/update
     * validation agree on who is eligible to interview.
     */
    private const Role INTERVIEWER_ROLE = Role::Instructor;

    /**
     * List interview slots.
     *
     * The paginated slot pool, soonest-first, each with its resolved interviewer and
     * whether it is still open. Gated on `interview.schedule`.
     *
     * @queryParam q string Normalized bilingual location search. Example: Building
     * @queryParam id string Comma-separated slot ids. Example: 1,2
     * @queryParam interviewer_id string Comma-separated interviewer ids. Example: 4
     * @queryParam mode string Comma-separated modes. Example: in_person
     * @queryParam is_open string Derived availability, true or false. Example: true
     * @queryParam scheduled_at_min string Earliest scheduled timestamp. Example: 2026-01-01
     * @queryParam scheduled_at_max string Latest scheduled timestamp. Example: 2027-01-01
     * @queryParam duration_minutes_min integer Minimum duration in minutes. Example: 15
     * @queryParam duration_minutes_max integer Maximum duration in minutes. Example: 240
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: scheduled_at,-id
     * @queryParam page integer Page number; ignored by export. Example: 1
     * @queryParam per_page integer Page size, capped at 100; ignored by export. Example: 25
     *
     * @response 200 {"data": [{"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": true}], "links": {"first": "http://localhost/api/v1/interview-slots?page=1", "last": "http://localhost/api/v1/interview-slots?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 25, "total": 1, "last_page": 1}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(Request $request, Directory $directory, InterviewSlotCollection $collection): AnonymousResourceCollection
    {
        $query = $collection->query($request);
        $slots = $collection->selection($query)->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString();
        $this->attachInterviewers($slots->getCollection(), $directory);

        return InterviewSlotResource::collection($slots);
    }

    /**
     * Export interview slots.
     *
     * Every matching manager-readable slot, independent of pagination.
     *
     * @queryParam q string Normalized bilingual location search. Example: Building
     * @queryParam id string Comma-separated slot ids. Example: 1,2
     * @queryParam interviewer_id string Comma-separated interviewer ids. Example: 4
     * @queryParam mode string Comma-separated modes. Example: in_person
     * @queryParam is_open string Derived availability, true or false. Example: true
     * @queryParam scheduled_at_min string Earliest scheduled timestamp. Example: 2026-01-01
     * @queryParam scheduled_at_max string Latest scheduled timestamp. Example: 2027-01-01
     * @queryParam duration_minutes_min integer Minimum duration in minutes. Example: 15
     * @queryParam duration_minutes_max integer Maximum duration in minutes. Example: 240
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: scheduled_at,-id
     * @queryParam page integer Page number; ignored by export. Example: 1
     * @queryParam per_page integer Page size, capped at 100; ignored by export. Example: 25
     *
     * @response 200 scenario="XLSX" <<binary>>
     */
    public function export(Request $request, InterviewSlotExport $export): BinaryFileResponse
    {
        return $export->download($request);
    }

    /**
     * List assignable interviewers.
     *
     * The faculty Users (Instructors) an interview slot may be assigned to — the
     * source for the slot form's interviewer picker. Gated on `interview.schedule`.
     *
     * @response 200 {"data": [{"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function interviewers(Directory $directory): JsonResponse
    {
        $interviewers = array_map(
            static fn (UserSummary $summary): array => $summary->toArray(),
            $directory->usersByRole(self::INTERVIEWER_ROLE),
        );

        return response()->json(['data' => $interviewers]);
    }

    /**
     * Create an interview slot.
     *
     * One appointment in the pool: a future time, a duration, an assigned faculty
     * interviewer, and a mode with its coordinate (an in-person `location` or a virtual
     * `meeting_link`). Gated on `interview.schedule`.
     *
     * @bodyParam scheduled_at string required The appointment time (ISO-8601), in the future. Example: 2026-07-20T10:00:00Z
     * @bodyParam duration_minutes integer required Length in minutes (15–240). Example: 30
     * @bodyParam interviewer_id integer required A faculty (Instructor) User to conduct it. Example: 4
     * @bodyParam mode string required One of in_person, virtual. Example: in_person
     * @bodyParam location string The in-person location; required when mode is in_person. Example: Building C, Room 204
     * @bodyParam meeting_link string The virtual meeting link; required when mode is virtual. Example: https://meet.ejust.edu.eg/abc
     *
     * @response 201 {"data": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": true}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 422 scenario="not a faculty interviewer" {"message": "The selected interviewer is not a faculty member.", "errors": {"interviewer_id": ["The selected interviewer is not a faculty member."]}}
     */
    public function store(Request $request, Directory $directory): JsonResponse
    {
        $slot = InterviewSlot::create($this->validated($request, $directory));

        $this->attachInterviewers(collect([$slot]), $directory);

        return (new InterviewSlotResource($slot))->response()->setStatusCode(201);
    }

    /**
     * Update an interview slot.
     *
     * Change an **open** slot's time, duration, interviewer, or mode. A slot with an
     * interview already booked into it cannot be edited (409) — its time/interviewer
     * are what the applicant was told. Gated on `interview.schedule`.
     *
     * @urlParam interview_slot_id integer required The slot's id. Example: 1
     *
     * @bodyParam scheduled_at string required The appointment time (ISO-8601), in the future. Example: 2026-07-21T14:00:00Z
     * @bodyParam duration_minutes integer required Length in minutes (15–240). Example: 45
     * @bodyParam interviewer_id integer required A faculty (Instructor) User to conduct it. Example: 4
     * @bodyParam mode string required One of in_person, virtual. Example: virtual
     * @bodyParam location string The in-person location; required when mode is in_person. Example: Building C, Room 204
     * @bodyParam meeting_link string The virtual meeting link; required when mode is virtual. Example: https://meet.ejust.edu.eg/abc
     *
     * @response 200 {"data": {"id": 1, "scheduled_at": "2026-07-21T14:00:00+00:00", "duration_minutes": 45, "mode": "virtual", "location": null, "meeting_link": "https://meet.ejust.edu.eg/abc", "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": true}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 scenario="slot is booked" {"message": "This slot has an interview booked and cannot be changed."}
     */
    public function update(Request $request, InterviewSlot $interviewSlot, Directory $directory): InterviewSlotResource
    {
        $this->assertOpen($interviewSlot);

        $interviewSlot->update($this->validated($request, $directory));

        $this->attachInterviewers(collect([$interviewSlot]), $directory);

        return new InterviewSlotResource($interviewSlot);
    }

    /**
     * Delete an interview slot.
     *
     * Remove an **open** slot from the pool. A slot with an interview booked into it
     * cannot be deleted (409). Gated on `interview.schedule`.
     *
     * @urlParam interview_slot_id integer required The slot's id. Example: 1
     *
     * @response 204
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 scenario="slot is booked" {"message": "This slot has an interview booked and cannot be changed."}
     */
    public function destroy(InterviewSlot $interviewSlot): JsonResponse
    {
        $this->assertOpen($interviewSlot);

        $interviewSlot->delete();

        return response()->json(status: 204);
    }

    /**
     * Validate a slot payload, including that the chosen interviewer is a faculty User
     * (holds the Instructor role). The interviewer set is read from the Identity\Public
     * directory, so a non-faculty id is a 422 — routing interviews to a non-interviewer
     * would silently break the interviewer's queue and the row-level evaluation gate.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Directory $directory): array
    {
        $interviewerIds = array_map(
            static fn ($summary): int => $summary->id,
            $directory->usersByRole(self::INTERVIEWER_ROLE),
        );

        return $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'interviewer_id' => ['required', 'integer', Rule::in($interviewerIds)],
            'mode' => ['required', 'string', 'in:in_person,virtual'],
            'location' => ['required_if:mode,in_person', 'nullable', 'string', 'max:255'],
            'meeting_link' => ['required_if:mode,virtual', 'nullable', 'url', 'max:255'],
        ], [
            'interviewer_id.in' => __('admissions.interview.not_faculty'),
        ]);
    }

    /** Refuse to mutate a slot that already has an interview booked into it (409). */
    private function assertOpen(InterviewSlot $slot): void
    {
        if (! $slot->isOpen()) {
            throw new ConflictHttpException(__('admissions.interview.slot_booked'));
        }
    }
}
