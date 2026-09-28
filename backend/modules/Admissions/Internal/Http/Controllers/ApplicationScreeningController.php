<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\NotifiesApplicant;
use Modules\Admissions\Internal\Http\Concerns\ResolvesQueuePrograms;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationStatusTransitionResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Support\ApplicationAccess;
use Modules\Admissions\Internal\Support\ApplicationCollection;
use Modules\Admissions\Internal\Support\ApplicationExport;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Notifications\Public\Contracts\Notifier;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
    use NotifiesApplicant;
    use ResolvesQueuePrograms;

    /**
     * Screening queue.
     *
     * Defaults to Applications awaiting Student Affairs screening (`under_sa_review`).
     * An explicit status selection supports Pipeline drill-through across stages,
     * within the same actor scope. Oldest-first, each with identity, primary Program, and a
     * **derived age** — whole days waited since entering review, computed from the
     * timeline, never stored. Gated on `application.screen`.
     *
     * @queryParam q string Normalized bilingual name or identifier search. Example: Ahmed
     * @queryParam status string Comma-separated Application statuses; defaults to under_sa_review. Example: accepted
     * @queryParam program_id string Program ids. Example: 1,2
     * @queryParam faculty_id string Faculty ids, narrowing the actor scope. Example: 1,2
     * @queryParam admission_cycle_id string Cycle ids. Example: 1
     * @queryParam degree_level string Degree levels. Example: MSc
     * @queryParam age_days_min integer Minimum whole days in current review. Example: 7
     * @queryParam age_days_max integer Maximum whole days in current review. Example: 30
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: -age_days,name_en
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size, capped at 100. Example: 25
     *
     * @response 200 {"data": [{"id": 1, "status": "under_sa_review", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-10T10:00:00+00:00", "age_days": 4}], "links": {"first": "http://localhost/api/v1/applications?page=1", "last": "http://localhost/api/v1/applications?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 25, "total": 1, "last_page": 1}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    #[ResponseField('data.age_days', 'integer', nullable: true)]
    public function index(Request $request, ProgramCatalog $catalog, ApplicationCollection $collection): AnonymousResourceCollection
    {
        $applications = $collection->page($request, $collection->query($request), $this->selectedStages($request));

        // Resolve every queued application's primary Program in one facade read
        // (ADR-0012) and stash it per row, so rendering the queue is a single Catalog
        // read rather than one per application (shared with the Dean queue).
        $this->attachPrimaryPrograms($applications->getCollection(), $catalog);

        return ApplicationQueueResource::collection($applications);
    }

    /**
     * Export screening queue.
     *
     * Canonical authorized columns, localized to the request language. Includes
     * all scoped matches; page and per_page never limit the file. No evidence files.
     *
     * @queryParam q string Normalized bilingual name or identifier search. Example: Ahmed
     * @queryParam status string Comma-separated statuses. Example: under_sa_review
     * @queryParam program_id string Program ids. Example: 1,2
     * @queryParam faculty_id string Faculty ids, narrowing the actor scope. Example: 1,2
     * @queryParam admission_cycle_id string Cycle ids. Example: 1
     * @queryParam degree_level string Degree levels. Example: MSc
     * @queryParam age_days_min integer Minimum whole days in current review. Example: 7
     * @queryParam age_days_max integer Maximum whole days in current review. Example: 30
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: -age_days,name_en
     * @queryParam page integer Ignored by export. Example: 1
     * @queryParam per_page integer Ignored by export. Example: 25
     *
     * @response 200 <<binary>>
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function export(Request $request, ApplicationExport $export): BinaryFileResponse
    {
        return $export->download($request, $this->selectedStages($request));
    }

    /** @return list<ApplicationStatus> */
    private function selectedStages(Request $request): array
    {
        return $request->filled('status') ? ApplicationStatus::cases() : [ApplicationStatus::UnderSAReview];
    }

    /**
     * Review an application.
     *
     * The full application — the Step-1 snapshot, resolved Program(s), and the
     * uploaded documents — for a reviewer to assess completeness and authenticity
     * before acting. Gated on `application.screen` and the same Faculty scope as the queue.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @response 200 {"data": {"id": 1, "status": "under_sa_review", "admission_cycle_id": 1, "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "id_type": "national_id", "phone": "+201000000000", "date_of_birth": "1998-05-20", "nationality": "EG", "gender": "Female", "address_country": "Egypt", "address_city": "Alexandria", "address_street": "15 Corniche Road", "previous_university": "Cairo University", "graduation_certificate_name": "BSc in Computer Science", "graduation_date": "2020-07-01", "previous_degree": "BSc", "previous_major": "Computer Science", "graduation_gpa": "3.50", "graduation_cgpa": "3.45", "gpa_scale": "4.0", "is_ejust_graduate": false, "applying_for_degree": "MSc", "english_test": "IELTS", "english_score": "7.50", "native_arabic_speaker": true, "submitted_at": "2026-07-14T10:00:00+00:00", "primary_program_id": 1, "alternative_program_id": 2, "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "alternative_program": {"id": 2, "code": "MSC-EE", "name_en": "MSc in Electrical Engineering", "name_ar": "ماجستير الهندسة الكهربية", "degree_level": "MSc", "department_name_en": "Electrical Engineering", "department_name_ar": "الهندسة الكهربية", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": null, "conditions": "Submit the final transcript by September.", "waitlist_position": 2, "documents": [{"id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "document_type": "graduation_certificate", "original_filename": "certificate.pdf", "mime": "application/pdf", "size_bytes": 24576, "uploaded_at": "2026-07-14T10:00:00+00:00", "download_url": "http://localhost/api/v1/documents/01ARZ3NDEKTSV4RRFFQ69G5FAV"}]}}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "No query results for model."}
     */
    public function show(Request $request, Application $application, ApplicationAccess $access): ApplicationResource
    {
        abort_unless($access->reviews((int) $request->user()?->getAuthIdentifier(), $application), 403);
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
     * @response 409 scenario="not under review" {"message": "This application is not under Student Affairs review."}
     * @response 422 scenario="missing note" {"message": "The note field is required when action is return.", "errors": {"note": ["The note field is required when action is return."]}}
     */
    public function screen(Request $request, Application $application, Notifier $notifier, ApplicationAccess $access): ApplicationResource
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
        DB::transaction(function () use ($application, $validated, $actorId, $note, $notifier, $access): void {
            // Re-read scope and state while holding the row lock; route bindings may be stale.
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($access->reviews($actorId, $locked), 403);
            if ($locked->status !== ApplicationStatus::UnderSAReview) {
                throw new ConflictHttpException(__('admissions.transition.not_under_sa_review'));
            }
            // Keep the request's bound status for the transition's stale-write check.
            $application->primary_program_id = $locked->primary_program_id;
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
     * owning applicant or a scoped reviewer (`application.screen`); anyone else → 403.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @response 200 {"data": [{"id": 1, "from_status": "email_verified", "to_status": "under_sa_review", "actor_user_id": 5, "note": null, "occurred_at": "2026-07-10T10:00:00+00:00"}, {"id": 2, "from_status": "under_sa_review", "to_status": "returned_for_revision", "actor_user_id": 2, "note": "Please re-upload a clearer transcript.", "occurred_at": "2026-07-11T09:00:00+00:00"}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function timeline(Request $request, Application $application, ApplicationAccess $access): AnonymousResourceCollection
    {
        abort_unless($access->reads((int) $request->user()?->getAuthIdentifier(), $application), 403);

        return ApplicationStatusTransitionResource::collection($application->statusTransitions);
    }

    /** Return for revision, then notify the applicant of the reviewer's instructions. */
    private function returnForRevision(Application $application, int $actorId, string $note, Notifier $notifier): void
    {
        $application->returnForRevision($actorId, $note);
        $this->notifyReturnedForRevision($notifier, $application, $note);
    }

    /** Reject at screening, then notify the applicant of the outcome and reason. */
    private function reject(Application $application, int $actorId, string $note, Notifier $notifier): void
    {
        $application->reject($actorId, $note);
        $this->notifyDecisionRecorded($notifier, $application, ApplicationStatus::Rejected, $note);
    }
}
