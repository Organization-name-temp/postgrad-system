<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admissions\Internal\Http\Concerns\IdentifiesAdministrator;
use Modules\Admissions\Internal\Http\Concerns\ResolvesInterviewers;
use Modules\Admissions\Internal\Http\Resources\InterviewResource;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\Directory;

/**
 * @group Admissions
 *
 * The interviewer's side of an interview (ticket 09): the faculty member assigned to a
 * slot works their list of upcoming interviews and records an evaluation (a score and
 * notes) for each. Authorization here is **row-level**, not permission-based — an
 * interviewer holds no reviewer permission, so the surface is open to any authenticated
 * user, and each action is allowed only for the interviewer the slot is assigned to:
 * anyone else recording an evaluation gets a 403, so evaluations stay trustworthy.
 *
 * A System/Super Administrator (R09/R10) is the one exception (ADR-0015): they may read
 * every interview and record or override any evaluation. An admin-authored score is
 * written to the Audit Log (actor = the admin), which is what preserves accountability
 * once the interviewer-attestation model is bypassed — an interviewer scoring their own
 * assignment stays un-audited, as before.
 */
class InterviewEvaluationController
{
    use IdentifiesAdministrator;
    use ResolvesInterviewers;

    /**
     * Interviews assigned to me.
     *
     * The interviews booked into the signed-in user's own slots, soonest-first, each
     * with the applicant's identity and the slot details — the interviewer's queue.
     * Empty for a user who is not the assigned interviewer on any slot. An administrator
     * sees **every** interview instead (ADR-0015), so they can reach the evaluation
     * write-path through this same screen.
     *
     * @response 200 {"data": [{"id": 1, "application_id": 1, "status": "confirmed", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": null, "evaluation_notes": null, "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "is_open": false}, "applicant": {"name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "status": "interview_scheduled"}}]}
     */
    public function index(Request $request, Directory $directory): AnonymousResourceCollection
    {
        $interviewerId = (int) $request->user()?->getAuthIdentifier();
        $isAdmin = $this->actingAsAdministrator($request, $directory);

        $interviews = Interview::query()
            ->when(
                ! $isAdmin,
                fn ($query) => $query->whereHas('slot', fn ($slot) => $slot->where('interviewer_id', $interviewerId)),
            )
            ->with(['slot', 'application'])
            ->get();

        $this->attachInterviewers($interviews->pluck('slot')->filter()->values(), $directory);

        return InterviewResource::collection($interviews);
    }

    /**
     * Record my evaluation.
     *
     * The assigned interviewer records a score (1–10) and optional notes for the
     * interview. Only the interviewer the slot is assigned to may do this — anyone else
     * is a 403 — **except** an administrator (R09/R10), who may record or override any
     * interview's evaluation, that write attributed to them in the Audit Log (ADR-0015).
     * An evaluation cannot be recorded once the interview is complete (409).
     *
     * @urlParam interview_id integer required The interview's id. Example: 1
     *
     * @bodyParam score integer required The interview score, 1–10. Example: 8
     * @bodyParam notes string Free-text notes on the interview. Example: Strong candidate; clear research direction.
     *
     * @response 200 {"data": {"id": 1, "application_id": 1, "status": "confirmed", "reschedule_count": 0, "can_reschedule": true, "evaluation_score": 8, "evaluation_notes": "Strong candidate; clear research direction.", "completed_at": null, "slot": {"id": 1, "scheduled_at": "2026-07-20T10:00:00+00:00", "duration_minutes": 30, "mode": "in_person", "location": "Building C, Room 204", "meeting_link": null, "interviewer_id": 4, "interviewer": null, "is_open": false}, "applicant": null}}
     * @response 403 scenario="not the assigned interviewer" {"message": "This action is unauthorized."}
     * @response 409 scenario="already complete" {"message": "This interview has already been completed."}
     * @response 422 scenario="bad score" {"message": "The score field is required.", "errors": {"score": ["The score field is required."]}}
     */
    public function store(Request $request, Interview $interview, Directory $directory, AuditLogger $auditLogger): InterviewResource
    {
        $interview->loadMissing('slot');

        // Row-level authorization: only the slot's assigned interviewer may evaluate —
        // an administrator (R09/R10) is the sole exception (ADR-0015).
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $interviewerId = $interview->slot?->interviewer_id;
        $isAssignedInterviewer = $interviewerId !== null && $interviewerId === $actorId;

        if (! $isAssignedInterviewer && ! $this->actingAsAdministrator($request, $directory)) {
            abort(403);
        }

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $interview->recordEvaluation((int) $validated['score'], $validated['notes'] ?? null);

        // Reaching here without being the assigned interviewer means the administrative
        // override was used; that write is attributed to the admin in the Audit Log, so
        // a score authored by someone other than the assigned interviewer is accountable
        // (ADR-0015) — whether recorded on their behalf or overriding an existing score.
        // The interviewer's own scoring is not logged.
        if (! $isAssignedInterviewer) {
            $auditLogger->record(
                action: 'interview.evaluation_recorded_by_admin',
                actorUserId: $actorId,
                metadata: [
                    'application_id' => (int) $interview->application_id,
                    'assigned_interviewer_id' => $interviewerId,
                    'score' => (int) $validated['score'],
                ],
                subjectType: 'interview',
                subjectId: (string) $interview->getKey(),
            );
        }

        if ($interview->slot !== null) {
            $this->attachInterviewers(collect([$interview->slot]), $directory);
        }

        return new InterviewResource($interview);
    }
}
