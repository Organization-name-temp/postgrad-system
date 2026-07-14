<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\NotifiesApplicant;
use Modules\Admissions\Internal\Http\Concerns\ResolvesQueuePrograms;
use Modules\Admissions\Internal\Http\Resources\ApplicationQueueResource;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

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

    /**
     * Dean review queue.
     *
     * The `under_dean_review` applications whose primary Program's Faculty is among
     * the signed-in Dean's assigned Faculties, oldest-first, each with the applicant's
     * identity, resolved primary Program, and days waited since entering Dean review.
     * A Dean with no assigned Faculty (or a non-Dean reviewer) sees an empty queue.
     *
     * @response 200 {"data": [{"id": 1, "status": "under_dean_review", "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "applying_for_degree": "MSc", "primary_program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير هندسة الحاسوب", "degree_level": "MSc", "department_name_en": "Computer Engineering", "department_name_ar": "هندسة الحاسوب", "faculty_name_en": "Faculty of Engineering", "faculty_name_ar": "كلية الهندسة"}, "finalized_at": "2026-07-10T10:00:00+00:00", "review_entered_at": "2026-07-11T10:00:00+00:00", "age_days": 3}]}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function index(Request $request, DeanAssignments $assignments, ProgramCatalog $catalog): AnonymousResourceCollection
    {
        $facultyIds = $assignments->facultiesForDean((int) $request->user()?->getAuthIdentifier());

        if ($facultyIds === []) {
            return ApplicationQueueResource::collection([]);
        }

        $applications = Application::query()
            ->with('statusTransitions')
            ->where('status', ApplicationStatus::UnderDeanReview)
            ->orderBy('finalized_at')
            ->orderBy('id')
            ->get();

        // One facade read for the whole queue (ADR-0012): resolve and stash every
        // application's primary Program (shared with the SA queue), then keep only
        // those whose Faculty this Dean covers.
        $this->attachPrimaryPrograms($applications, $catalog);

        $scoped = $applications
            ->filter(function (Application $application) use ($facultyIds): bool {
                $summary = $application->getAttribute('resolved_primary_program');

                return $summary !== null && in_array($summary->facultyId, $facultyIds, true);
            })
            ->values();

        return ApplicationQueueResource::collection($scoped);
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
        Notifier $notifier,
    ): ApplicationResource {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ]);

        // Routing integrity before state: an application outside this Dean's Faculties
        // is refused (403) whatever its status, so a Dean cannot act on another
        // Faculty's applicants even by guessing an id.
        $this->authorizeFacultyScope($request, $application, $assignments, $catalog);

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

    /**
     * Assert the signed-in Dean may act on this application — its primary Program's
     * Faculty is among the Dean's assigned Faculties. A dropped/absent Program
     * reference (no FK, ADR-0012) or an out-of-faculty Program is refused with a 403.
     */
    private function authorizeFacultyScope(
        Request $request,
        Application $application,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
    ): void {
        $programId = $application->primary_program_id;
        $summary = $programId === null ? null : $catalog->find($programId);
        $facultyIds = $assignments->facultiesForDean((int) $request->user()?->getAuthIdentifier());

        if ($summary === null || ! in_array($summary->facultyId, $facultyIds, true)) {
            abort(403);
        }
    }
}
