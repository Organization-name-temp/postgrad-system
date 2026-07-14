<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationStatusTransitionResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Enums\Permission;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * @group Admissions
 *
 * Student Affairs screening (ticket 07) — the first reviewer surface over the
 * application workflow. A holder of `application.screen` works a queue of finalized
 * applications awaiting screening (with a derived age), opens one in full, and moves
 * it forward: **approve** (auto-forwarded to the Faculty's Dean), **return for
 * revision** (back to the applicant with instructions), or **reject** — each with a
 * note. Every move is a guarded transition recorded on the append-only timeline;
 * an illegal move is refused with a 409. The timeline itself is readable by the
 * owning applicant or any reviewer.
 *
 * The queue/detail/screen endpoints are gated on `application.screen` by route
 * middleware; the timeline authorizes owner-or-reviewer in the action, mirroring the
 * document streaming route (both are reachable by the applicant *and* reviewers).
 */
class ApplicationScreeningController
{
    /**
     * Screening queue.
     *
     * The applications awaiting Student Affairs screening (`under_sa_review`),
     * oldest-first, each with the applicant's identity, chosen primary Program, and a
     * **derived age** — whole days waited since entering review, computed from the
     * timeline, never stored. Gated on `application.screen`.
     *
     * @response 200 {"data": [{"id": 1, "status": "under_sa_review", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-10T10:00:00+00:00", "age_days": 4}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(ProgramCatalog $catalog): AnonymousResourceCollection
    {
        $applications = Application::query()
            ->with('statusTransitions')
            ->where('status', ApplicationStatus::UnderSAReview)
            ->orderBy('finalized_at')
            ->orderBy('id')
            ->get();

        // Resolve every queued application's primary Program in one facade read
        // (ADR-0012) and stash it per row, so rendering the queue is a single Catalog
        // read rather than one per application.
        $ids = $applications->pluck('primary_program_id')->filter()->unique()->values()->all();
        $summaries = $ids === [] ? [] : $catalog->findMany($ids);
        $applications->each(function (Application $application) use ($summaries): void {
            $id = $application->primary_program_id;
            $application->setAttribute('resolved_primary_program', $id === null ? null : ($summaries[$id] ?? null));
        });

        return ApplicationQueueResource::collection($applications);
    }

    /**
     * Review an application.
     *
     * The full application — the Step-1 snapshot, resolved Program(s), and the
     * uploaded documents — for a reviewer to assess completeness and authenticity
     * before acting. Gated on `application.screen`.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @response 200 {"data": {"id": 1, "status": "under_sa_review", "admission_cycle_id": 1, "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "phone": "+201000000000", "nationality": "Egyptian", "gender": "Female", "previous_degree": "BSc", "previous_university": "Cairo University", "graduation_gpa": "3.50", "applying_for_degree": "MSc", "english_test": "IELTS", "english_score": "7.50", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "documents": [{"id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "document_type": "graduation_certificate", "original_filename": "certificate.pdf", "mime": "application/pdf", "size_bytes": 24576, "uploaded_at": "2026-07-14T10:00:00+00:00", "download_url": "http://localhost/api/v1/documents/01ARZ3NDEKTSV4RRFFQ69G5FAV"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No query results for model."}
     */
    public function show(Application $application): ApplicationResource
    {
        $application->load('documents');

        return new ApplicationResource($application);
    }

    /**
     * Screen an application.
     *
     * The Student Affairs decision on a queued application: `approve` auto-advances it
     * through the transient `sa_approved` to `under_dean_review` in one action;
     * `return` sends it back to the applicant as `returned_for_revision` with the
     * reviewer's instructions; `reject` ends it as `rejected` with a reason. `return`
     * and `reject` require a note and notify the applicant. Any action on an
     * application not `under_sa_review` is an illegal transition (409). Gated on
     * `application.screen`.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @bodyParam action string required One of approve, return, reject. Example: approve
     * @bodyParam note string The reviewer's note; required for return and reject. Example: Please re-upload a clearer copy of your transcript.
     *
     * @response 200 scenario="approved" {"data": {"id": 1, "status": "under_dean_review"}}
     * @response 200 scenario="returned" {"data": {"id": 1, "status": "returned_for_revision"}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 scenario="not under review" {"message": "This application cannot move from draft to sa_approved."}
     * @response 422 scenario="missing note" {"message": "The note field is required when action is return.", "errors": {"note": ["The note field is required when action is return."]}}
     */
    public function screen(Request $request, Application $application, Notifier $notifier): ApplicationResource
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,return,reject'],
            'note' => ['required_if:action,return,reject', 'nullable', 'string', 'max:2000'],
        ]);

        $actorId = (int) $request->user()?->getAuthIdentifier();
        $note = $validated['note'] ?? null;

        // The transition (one or two rows) and the applicant notification commit
        // together — an approved/returned/rejected application never rests without
        // the move recorded, nor the applicant told, half-done. `action` is validated
        // to exactly these three values, so the final branch is `reject`.
        DB::transaction(function () use ($application, $validated, $actorId, $note, $notifier): void {
            if ($validated['action'] === 'approve') {
                $application->approveForDeanReview($actorId, $note);
            } elseif ($validated['action'] === 'return') {
                $this->returnForRevision($application, $actorId, (string) $note, $notifier);
            } else {
                $this->reject($application, $actorId, (string) $note, $notifier);
            }
        });

        return new ApplicationResource($application);
    }

    /**
     * Status timeline.
     *
     * An application's append-only status history (from/to/note/when), oldest-first —
     * the applicant's visible progress and the decision audit trail. Readable by the
     * owning applicant or any reviewer (`application.screen`); anyone else → 403.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "from_status": "email_verified", "to_status": "under_sa_review", "actor_user_id": 5, "note": null, "occurred_at": "2026-07-10T10:00:00+00:00"}, {"id": 2, "from_status": "under_sa_review", "to_status": "returned_for_revision", "actor_user_id": 2, "note": "Please re-upload a clearer transcript.", "occurred_at": "2026-07-11T09:00:00+00:00"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function timeline(Request $request, Application $application): AnonymousResourceCollection
    {
        $user = $request->user();
        $owns = $user !== null && (int) $application->user_id === (int) $user->getAuthIdentifier();

        // The owner sees their own timeline; a reviewer sees any (application.screen).
        if (! $owns && ! Gate::allows(Permission::ScreenApplications->value)) {
            abort(403);
        }

        return ApplicationStatusTransitionResource::collection($application->statusTransitions);
    }

    /** Return for revision, then notify the applicant of the reviewer's instructions. */
    private function returnForRevision(Application $application, int $actorId, string $note, Notifier $notifier): void
    {
        $application->returnForRevision($actorId, $note);

        $notifier->notify((int) $application->user_id, NotificationType::ApplicationReturned, [
            'application_id' => $application->getKey(),
            'note' => $note,
        ]);
    }

    /** Reject at screening, then notify the applicant of the outcome and reason. */
    private function reject(Application $application, int $actorId, string $note, Notifier $notifier): void
    {
        $application->reject($actorId, $note);

        $notifier->notify((int) $application->user_id, NotificationType::DecisionRecorded, [
            'application_id' => $application->getKey(),
            'decision' => ApplicationStatus::Rejected->value,
            'reason' => $note,
        ]);
    }
}
