<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\ResolvesInterviewers;
use Modules\Admissions\Internal\Http\Concerns\ResolvesQueuePrograms;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\InterviewResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * Interview scheduling + completion (ticket 09) — the Student Affairs side of the
 * interview. Once the Dean has approved an applicant for interview (the application
 * rests in `interview_scheduled`), Student Affairs books them into an open slot,
 * which creates the applicant's one {@see Interview} and notifies them of the details;
 * after the interview happens, Student Affairs marks it complete, advancing the
 * application to `interview_completed`. Both endpoints are gated on `interview.schedule`.
 *
 * Booking is *not* itself a status transition — the application is already
 * `interview_scheduled` — so its preconditions (right status, not already booked, slot
 * still open) are plain state guards surfaced as 409s. Completion, by contrast, is a
 * guarded transition on the application, performed together with the interview's
 * completion stamp in one transaction.
 */
class InterviewSchedulingController
{
    use ResolvesInterviewers;
    use ResolvesQueuePrograms;

    /**
     * Booked interviews.
     *
     * Every interview that has been booked, most-recent-first, each with the applicant
     * being interviewed, the slot details, its confirmation status, and any recorded
     * evaluation — Student Affairs' view for tracking interviews and marking them
     * complete. Gated on `interview.schedule`.
     *
     * @response 200 {"data": [{"id": 1, "application_id": 1, "status": "confirmed", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": 8, "evaluation_notes": "Strong candidate.", "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": false}, "applicant": {"name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "status": "interview_scheduled"}}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function booked(Directory $directory): AnonymousResourceCollection
    {
        $interviews = Interview::query()
            ->with(['slot', 'application'])
            ->orderByDesc('id')
            ->get();

        $this->attachInterviewers($interviews->pluck('slot')->filter()->values(), $directory);

        return InterviewResource::collection($interviews);
    }

    /**
     * Applications awaiting interview scheduling.
     *
     * The Dean-approved applications (`interview_scheduled`) that have **not** yet been
     * booked into a slot, oldest-first — Student Affairs' scheduling worklist, each with
     * the applicant's identity, resolved primary Program, and days waited since Dean
     * approval. Gated on `interview.schedule`.
     *
     * @response 200 {"data": [{"id": 1, "status": "interview_scheduled", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": null, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-12T10:00:00+00:00", "age_days": 3}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(ProgramCatalog $catalog): AnonymousResourceCollection
    {
        $applications = Application::query()
            ->with('statusTransitions')
            ->where('status', ApplicationStatus::InterviewScheduled)
            ->whereDoesntHave('interview')
            ->orderBy('finalized_at')
            ->orderBy('id')
            ->get();

        $this->attachPrimaryPrograms($applications, $catalog);

        return ApplicationQueueResource::collection($applications);
    }

    /**
     * Schedule an applicant into an interview slot.
     *
     * Books the applicant's interview into an open slot and notifies them of the
     * details. Legal only while the application is `interview_scheduled` and has no
     * interview yet, and the slot is still open — otherwise 409. Gated on
     * `interview.schedule`.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @bodyParam interview_slot_id integer required The open slot to book. Example: 1
     *
     * @response 201 {"data": {"id": 1, "application_id": 1, "status": "pending_confirmation", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": null, "evaluation_notes": null, "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": null, "is_open": false}, "applicant": null}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 scenario="not ready / already booked / slot taken" {"message": "This application is not awaiting interview scheduling."}
     * @response 422 scenario="no such slot" {"message": "The selected interview slot id is invalid.", "errors": {"interview_slot_id": ["The selected interview slot id is invalid."]}}
     */
    public function store(Request $request, Application $application, Notifier $notifier): JsonResponse
    {
        $validated = $request->validate([
            'interview_slot_id' => ['required', 'integer', Rule::exists('interview_slots', 'id')],
        ]);

        if (! $application->isAwaitingInterviewScheduling()) {
            throw new ConflictHttpException(__('admissions.interview.not_awaiting_scheduling'));
        }

        if ($application->interview()->exists()) {
            throw new ConflictHttpException(__('admissions.interview.already_scheduled'));
        }

        $slot = InterviewSlot::query()->findOrFail($validated['interview_slot_id']);

        if (! $slot->isOpen()) {
            throw new ConflictHttpException(__('admissions.interview.slot_booked'));
        }

        // The booking and the applicant notification commit together: the applicant is
        // never told of an interview that did not persist, nor left un-notified of one
        // that did.
        $interview = DB::transaction(function () use ($application, $slot, $notifier): Interview {
            $interview = Interview::create([
                'application_id' => $application->getKey(),
                'interview_slot_id' => $slot->getKey(),
            ]);

            $notifier->notify((int) $application->user_id, NotificationType::InterviewScheduled, [
                'application_id' => $application->getKey(),
                'scheduled_at' => $slot->scheduled_at->toIso8601String(),
                'mode' => $slot->mode->value,
                'location' => $slot->location,
                'meeting_link' => $slot->meeting_link,
            ]);

            return $interview;
        });

        $interview->setRelation('slot', $slot);

        return (new InterviewResource($interview))->response()->setStatusCode(201);
    }

    /**
     * Mark an interview complete.
     *
     * Records that the interview happened and advances the application
     * `interview_scheduled` → `interview_completed`, from where the Dean records the
     * final decision. Completing an already-complete interview, or one whose
     * application is not `interview_scheduled`, is a 409. Gated on `interview.schedule`.
     *
     * @urlParam interview_id integer required The interview's id. Example: 1
     *
     * @response 200 {"data": {"id": 1, "application_id": 1, "status": "confirmed", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": 8, "evaluation_notes": "Strong candidate.", "completed_at": "2026-07-20T11:00:00+00:00", "slot": null, "applicant": null}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 scenario="already complete / wrong status" {"message": "This application cannot move from interview_completed to interview_completed."}
     */
    public function complete(Request $request, Interview $interview): InterviewResource
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $application = $interview->application()->firstOrFail();

        // The application transition and the interview completion stamp commit together
        // — the application never advances without the interview being marked done.
        DB::transaction(function () use ($application, $interview, $actorId): void {
            $application->completeInterview($actorId);
            $interview->markCompleted();
        });

        return new InterviewResource($interview);
    }
}
