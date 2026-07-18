<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Http\Concerns\ResolvesCurrentApplication;
use Modules\Admissions\Internal\Http\Concerns\ResolvesInterviewers;
use Modules\Admissions\Internal\Http\Resources\InterviewResource;
use Modules\Admissions\Internal\Http\Resources\InterviewSlotResource;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Public\Contracts\Directory;

/**
 * @group Admissions
 *
 * The applicant's side of their interview (ticket 09). Once Student Affairs has booked
 * a slot, the applicant sees the details, then either **confirms** the interview or
 * requests **exactly one reschedule** into another open slot (a second reschedule is
 * refused — BR-11). These act on the signed-in applicant's own interview implicitly —
 * no id in the path — so the surface needs only an authenticated session, never a
 * reviewer permission.
 */
class ApplicantInterviewController
{
    use ResolvesCurrentApplication;
    use ResolvesInterviewers;

    /**
     * My interview.
     *
     * The interview booked for the signed-in applicant's current application — the
     * when/where/who, the confirmation status, and whether a reschedule is still
     * available. 404 when none has been scheduled yet.
     *
     * @response 200 {"data": {"id": 1, "application_id": 1, "status": "pending_confirmation", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": null, "evaluation_notes": null, "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": false}, "applicant": null}}
     * @response 404 {"message": "No query results for model."}
     */
    public function show(Request $request, Directory $directory): InterviewResource
    {
        return new InterviewResource($this->resolveInterview($request, $directory));
    }

    /**
     * Open slots to reschedule into.
     *
     * The interview slots still open, soonest-first — the applicant's picker for their
     * one reschedule. Same shape as the Student Affairs slot list.
     *
     * @response 200 {"data": [{"id": 2, "scheduled_at": "2026-07-22T13:00:00+00:00", "duration_minutes": 30, "mode": "virtual", "location": null, "meeting_link": "https://meet.ejust.edu.eg/abc", "interviewer_id": 5, "interviewer": {"id": 5, "name": "Dr. Second Instructor", "email": "instructor2@ejust.test"}, "is_open": true}]}
     */
    public function openSlots(Directory $directory): AnonymousResourceCollection
    {
        $slots = InterviewSlot::query()
            ->open()
            ->with('interview')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $this->attachInterviewers($slots, $directory);

        return InterviewSlotResource::collection($slots);
    }

    /**
     * Confirm my interview.
     *
     * The applicant accepts the booked interview: `pending_confirmation` → `confirmed`.
     * Confirming an already-confirmed interview is a 409.
     *
     * @response 200 {"data": {"id": 1, "application_id": 1, "status": "confirmed", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": null, "evaluation_notes": null, "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": null, "is_open": false}, "applicant": null}}
     * @response 404 {"message": "No query results for model."}
     * @response 409 scenario="nothing to confirm" {"message": "This interview is not awaiting confirmation."}
     */
    public function confirm(Request $request, Directory $directory): InterviewResource
    {
        $interview = $this->resolveInterview($request, $directory);
        $interview->confirm();

        return new InterviewResource($interview);
    }

    /**
     * Reschedule my interview.
     *
     * Move the interview to another open slot, using the applicant's single allowed
     * reschedule (BR-11); it returns to `pending_confirmation` for the new slot. A
     * second reschedule is refused (409). The target must be an open slot (422/409).
     *
     * @bodyParam interview_slot_id integer required The open slot to move to. Example: 2
     *
     * @response 200 {"data": {"id": 1, "application_id": 1, "status": "pending_confirmation", "reschedule_count": 1, "can_reschedule": false, "evaluation_score": null, "evaluation_notes": null, "completed_at": null, "slot": {"id": 2, "scheduled_at": "2026-07-22T13:00:00+00:00", "duration_minutes": 30, "mode": "virtual", "location": null, "meeting_link": "https://meet.ejust.edu.eg/abc", "interviewer_id": 5, "interviewer": null, "is_open": false}, "applicant": null}}
     * @response 404 {"message": "No query results for model."}
     * @response 409 scenario="reschedule already used / slot taken" {"message": "You have already rescheduled this interview once."}
     * @response 422 scenario="no such open slot" {"message": "The selected interview slot id is invalid.", "errors": {"interview_slot_id": ["The selected interview slot id is invalid."]}}
     */
    public function reschedule(Request $request, Directory $directory): InterviewResource
    {
        $validated = $request->validate([
            'interview_slot_id' => [
                'required',
                'integer',
                // Must be an open slot: absent from the `interviews` table's slot ids.
                Rule::exists('interview_slots', 'id')
                    ->whereNotIn('id', Interview::query()->pluck('interview_slot_id')->all()),
            ],
        ]);

        $interview = $this->resolveInterview($request, $directory);
        $slot = InterviewSlot::query()->findOrFail($validated['interview_slot_id']);

        $interview->rescheduleTo($slot);
        $interview->setRelation('slot', $slot->fresh());

        return new InterviewResource($interview);
    }

    /**
     * The signed-in applicant's interview, with its slot loaded and the interviewer
     * resolved for display. 404 when the applicant has no interview scheduled.
     */
    private function resolveInterview(Request $request, Directory $directory): Interview
    {
        $application = $this->currentApplication($request);
        $interview = $application->interview()->with('slot')->firstOrFail();

        if ($interview->slot !== null) {
            $this->attachInterviewers(collect([$interview->slot]), $directory);
        }

        return $interview;
    }
}
