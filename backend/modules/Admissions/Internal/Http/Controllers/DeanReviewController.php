<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Admissions\Internal\Enums\ApplicationCollectionScope;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\NotifiesApplicant;
use Modules\Admissions\Internal\Http\Concerns\ResolvesQueuePrograms;
use Modules\Admissions\Internal\Http\Concerns\ScopesToDeanFaculty;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Support\ApplicationCollection;
use Modules\Admissions\Internal\Support\ApplicationExport;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Admissions
 *
 * Dean review (ticket 08) — the faculty-scoped reviewer surface. A Dean works a
 * queue of the Student-Affairs-approved applications (`under_dean_review`) that
 * belong to **their** Faculty, and either approves one for interview
 * (`interview_scheduled`) or rejects it (`rejected`) with a reason; both notify the
 * applicant.
 *
 * Routing integrity is enforced, not assumed: an application's Faculty is resolved
 * `application → primary_program_id → facultyId` through the Catalog\Public
 * {@see ProgramCatalog} facade (ADR-0012), and the Dean's own Faculties come from
 * the Identity\Public {@see DeanAssignments} facade. A Dean acting on an application
 * outside their Faculties gets a 403 — so does anyone (e.g. Student Affairs) who
 * holds `application.screen` but has no Faculty assignment, which empties their queue
 * and refuses every action. The route middleware still gates the surface on
 * `application.screen`; the faculty scope is the finer, per-application check.
 */
class DeanReviewController
{
    use NotifiesApplicant;
    use ResolvesQueuePrograms;
    use ScopesToDeanFaculty;

    /**
     * Dean review queue.
     *
     * The `under_dean_review` applications whose primary Program's Faculty is among
     * the signed-in Dean's assigned Faculties, oldest-first, each with the applicant's
     * identity, resolved primary Program, and days waited since entering Dean review.
     * A Dean with no assigned Faculty (or a non-Dean reviewer) sees an empty queue.
     *
     * @queryParam q string Normalized bilingual name or identifier search. Example: Ahmed
     * @queryParam status string Comma-separated statuses within the queue. Example: under_dean_review
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
     * @response 200 {"data": [{"id": 1, "status": "under_dean_review", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-11T10:00:00+00:00", "age_days": 3}], "links": {"first": "http://localhost/api/v1/dean/applications?page=1", "last": "http://localhost/api/v1/dean/applications?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 25, "total": 1, "last_page": 1}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    #[ResponseField('data.age_days', 'integer', nullable: true)]
    public function index(Request $request, ProgramCatalog $catalog, ApplicationCollection $collection): AnonymousResourceCollection
    {
        // An administrator oversees every Faculty's queue unscoped (ADR-0015); a Dean
        // sees only the applications whose Faculty they are assigned to.
        $queue = $collection->page($request, $collection->query($request), [ApplicationStatus::UnderDeanReview], ApplicationCollectionScope::DeanFaculty);
        $this->attachPrimaryPrograms($queue->getCollection(), $catalog);

        return ApplicationQueueResource::collection($queue);
    }

    /**
     * Export Dean review queue.
     *
     * Canonical readable columns for every filtered row inside the Dean faculty scope.
     *
     * @queryParam q string Normalized bilingual name or identifier search. Example: Ahmed
     * @queryParam status string Comma-separated statuses within the queue. Example: under_dean_review
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
     * @response 200 scenario="XLSX" <<binary>>
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function export(Request $request, ApplicationExport $export): BinaryFileResponse
    {
        return $export->download($request, [ApplicationStatus::UnderDeanReview], ApplicationCollectionScope::DeanFaculty, 'dean/applications');
    }

    /**
     * Dean decision on an application.
     *
     * `approve` moves the application to `interview_scheduled` (the concrete slot
     * booking follows in interview scheduling); `reject` ends it as `rejected` with a
     * reason. Both notify the applicant. The application must belong to one of the
     * Dean's Faculties — otherwise 403, regardless of the action. Acting on an
     * application that is not `under_dean_review` is an illegal transition (409). The
     * surface is gated on `application.screen`.
     *
     * @urlParam application_id integer required The application's id. Example: 1
     *
     * @bodyParam action string required One of approve, reject. Example: approve
     * @bodyParam note string The Dean's note; required for reject (the reason). Example: The interview panel did not recommend admission.
     *
     * @response 200 scenario="approved for interview" {"data": {"id": 1, "status": "interview_scheduled"}}
     * @response 200 scenario="rejected" {"data": {"id": 1, "status": "rejected"}}
     * @response 403 scenario="out of faculty" {"message": "This action is unauthorized."}
     * @response 409 scenario="not under dean review" {"message": "This application cannot move from under_sa_review to interview_scheduled."}
     * @response 422 scenario="missing reason" {"message": "The note field is required when action is reject.", "errors": {"note": ["The note field is required when action is reject."]}}
     */
    public function review(
        Request $request,
        Application $application,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
        Directory $directory,
        Notifier $notifier,
    ): ApplicationResource {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ]);

        // Routing integrity before state: an application outside this Dean's Faculties
        // is refused (403) whatever its status, so a Dean cannot act on another
        // Faculty's applicants even by guessing an id. An administrator bypasses the
        // scope (ADR-0015).
        $this->authorizeDeanFacultyScope($request, $application, $assignments, $catalog, $directory);

        $actorId = (int) $request->user()?->getAuthIdentifier();
        $note = $validated['note'] ?? null;

        // The transition and the applicant notification commit together — the applicant
        // is never told of a move that did not persist, nor left un-notified of one that
        // did. `action` is validated to exactly these two values.
        DB::transaction(function () use ($application, $validated, $actorId, $note, $notifier): void {
            if ($validated['action'] === 'approve') {
                $application->approveForInterview($actorId, $note);
                $notifier->notify((int) $application->user_id, NotificationType::ApprovedForInterview, [
                    'application_id' => $application->getKey(),
                ]);
            } else {
                $application->reject($actorId, (string) $note);
                $this->notifyDecisionRecorded($notifier, $application, ApplicationStatus::Rejected, $note);
            }
        });

        return new ApplicationResource($application);
    }
}
