<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Http\Concerns\BuildsSheetPayload;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Grading\Internal\Support\OutstandingIncompleteQueue;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * @group Grading
 *
 * The Instructor's Incomplete-resolution surface (ADR-0023, ticket 05, SRS §4.7.3): the
 * outstanding Incompletes across every Offering they teach, each with its derived deadline
 * ({@see IncompleteDeadlineResolver}), and the lazily-opened `IncompleteResolution` Sheet round
 * that carries the outstanding mark through the same signature and Department-Head review as
 * any other Grade — {@see GradeSheetController} and {@see DepartmentHeadReviewController}'s
 * existing actions, reused unchanged rather than reimplemented, since both are already
 * kind-aware of a resolution round's one-line roster.
 *
 * Gated on `grades.enter`, the same as the rest of Sheet entry; authority over one Incomplete is
 * the assignment to its Enrollment's **original** Offering — the same rule
 * {@see GradeSheetController::authorizeOffering()} checks for every other Sheet action.
 */
class IncompleteController
{
    use BuildsSheetPayload;

    /**
     * My outstanding Incompletes.
     *
     * Every unresolved, unconverted Incomplete declared on an Offering the acting user
     * teaches, each with the Student it names and its derived resolution deadline — `null`
     * while the next Semester does not exist in the Calendar yet (declaring, and viewing, are
     * never blocked for an admin's omission).
     *
     * @response 200 {"data": [{"incomplete_id": 9, "enrollment_id": 5, "course_offering_id": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "completion_percentage": 80, "reason": "Hospitalised before the final project was due.", "deadline": "2026-11-03T00:00:00+00:00"}]}
     */
    public function outstanding(
        Request $request,
        OutstandingIncompleteQueue $queue,
        CourseCatalog $courses,
        StudentDirectory $students,
        Directory $directory,
        IncompleteDeadlineResolver $deadlines,
    ): JsonResponse {
        // "Which outstanding Incompletes are mine" is one question with one answer, shared with
        // the Dashboard Card that summarises this screen (phase 4.5, ticket 08), so the two can
        // never disagree about what is waiting on the acting Instructor.
        ['incompletes' => $outstanding, 'enrollments' => $enrollmentsByIncomplete] =
            $queue->forInstructor($this->actingUserId($request));

        if ($outstanding === []) {
            return response()->json(['data' => []]);
        }

        $coursesById = $courses->findMany(array_values(array_unique(array_map(
            fn (EnrollmentSummary $enrollment): int => $enrollment->courseId,
            $enrollmentsByIncomplete,
        ))));
        $summaries = $students->findManyByStudentId(array_values(array_unique(array_map(
            fn (EnrollmentSummary $enrollment): int => $enrollment->studentId,
            $enrollmentsByIncomplete,
        ))));
        $users = $directory->findMany(array_values(array_map(
            fn (StudentSummary $summary): int => $summary->userId,
            $summaries,
        )));

        $rows = [];
        foreach ($outstanding as $incomplete) {
            $enrollment = $enrollmentsByIncomplete[(int) $incomplete->getKey()];
            $summary = $summaries[$enrollment->studentId] ?? null;

            $rows[] = [
                'incomplete_id' => (int) $incomplete->getKey(),
                'enrollment_id' => $enrollment->id,
                'course_offering_id' => $enrollment->courseOfferingId,
                'course' => ($coursesById[$enrollment->courseId] ?? null)?->toArray(),
                'student' => [
                    'student_id' => $enrollment->studentId,
                    'university_id' => $summary?->universityId,
                    'name' => $this->studentName($enrollment, $summary, $users),
                ],
                'completion_percentage' => $incomplete->completion_percentage,
                'reason' => $incomplete->reason,
                'deadline' => $deadlines->forEnrollmentSemester($enrollment->semesterId)?->toIso8601String(),
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * Open (or resume) the Incomplete's resolution round.
     *
     * Lazily opens the `IncompleteResolution` Sheet round for one outstanding Incomplete — a
     * single line, the Incomplete's own Enrollment — or returns the round already open for it
     * (the Initial round's lazy-open precedent). From here, entering the mark, signing and
     * Department-Head finalization all behave exactly as ticket 03 established: the returned
     * Sheet id is used with {@see GradeSheetController::saveLine()}/{@see GradeSheetController::sign()}
     * and the Department Head's queue/show/finalize/return, all unchanged. A 409 when the
     * Incomplete has already been resolved or converted.
     *
     * @urlParam incomplete_id integer required The ID of the incomplete. Example: 9
     *
     * @response 200 scenario="freshly opened, no mark entered yet" {"data": {"sheet": {"id": 6, "course_offering_id": 3, "kind": "IncompleteResolution", "status": "Draft", "signed_at": null, "reviewed_at": null, "review_note": null}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "lines": [{"enrollment_id": 5, "course_status": "Incomplete", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": null, "incomplete": {"completion_percentage": 80, "reason": "Hospitalised before the final project was due."}}]}}
     * @response 200 scenario="the outstanding mark already entered" {"data": {"sheet": {"id": 6, "course_offering_id": 3, "kind": "IncompleteResolution", "status": "Draft", "signed_at": null, "reviewed_at": null, "review_note": null}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "lines": [{"enrollment_id": 5, "course_status": "Incomplete", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": {"mark": 88, "letter_grade": "B+", "gpa_points": 3.3}, "incomplete": {"completion_percentage": 80, "reason": "Hospitalised before the final project was due."}}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 409 scenario="already settled" {"message": "That incomplete has already been resolved or converted."}
     */
    public function resolution(
        Request $request,
        Incomplete $incomplete,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        StudentDirectory $students,
        Directory $directory,
    ): JsonResponse {
        $enrollment = $enrollments->findEnrollment((int) $incomplete->enrollment_id);
        abort_if($enrollment === null, 404, __('grading.offering.missing'));

        $offering = $enrollments->findOffering($enrollment->courseOfferingId);
        abort_if($offering === null, 404, __('grading.offering.missing'));
        abort_if($offering->instructorId !== $this->actingUserId($request), 403, __('grading.incomplete.not_yours'));
        abort_if(! $incomplete->isOutstanding(), 409, __('grading.incomplete.not_outstanding'));

        $sheet = $this->openResolutionSheet($incomplete, $offering);
        $roster = $this->rosterFor($sheet, $offering, $enrollments);

        return response()->json([
            'data' => [
                'sheet' => $this->sheetArray($sheet),
                'course' => $courses->find($offering->courseId)?->toArray(),
                'lines' => $this->lineArrays($sheet, $roster, $students, $directory),
            ],
        ]);
    }

    /**
     * The Incomplete's resolution round, created lazily on first open and returned unchanged on
     * every later one — the Initial round's `firstOrCreate` precedent, but keyed off the
     * Incomplete itself (one resolution round per Incomplete, not per Offering) since a
     * resolution round is never unique per Offering the way the Initial round is.
     */
    private function openResolutionSheet(Incomplete $incomplete, OfferingSummary $offering): GradeSheet
    {
        if ($incomplete->resolution_grade_sheet_id !== null) {
            $existing = GradeSheet::query()->find($incomplete->resolution_grade_sheet_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        $sheet = GradeSheet::create([
            'course_offering_id' => $offering->id,
            'kind' => GradeSheetKind::IncompleteResolution,
            'status' => GradeSheetStatus::Draft,
        ]);

        $incomplete->update(['resolution_grade_sheet_id' => $sheet->getKey()]);

        return $sheet;
    }

    private function actingUserId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
