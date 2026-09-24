<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Http\Concerns\BuildsSheetPayload;
use Modules\Grading\Internal\Http\Concerns\ScopesToDepartmentHead;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\CurrentGradeLookup;
use Modules\Grading\Internal\Support\DepartmentHeadSheetQueue;
use Modules\Grading\Public\Events\GradeFinalized;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * @group Grading
 *
 * The Department Head's review of a signed Grade Sheet (ADR-0023, ticket 03) — the second
 * half of SRS §4.7.1.4's two-party loop. A Head sees the Submitted rounds routed to them by
 * the Sheet's Offering's Course's Department ({@see ScopesToDepartmentHead}), reads every mark
 * and Incomplete alongside the Offering's grade distribution, and either finalizes the round —
 * making its Grades part of the academic record and pushing `earned` onto each Enrollment
 * (ADR-0022) — or returns it with a note. A Head can never write a mark: authorship of a Grade
 * always stays with the Instructor who taught the course, so nothing here touches a `Grade`
 * row's mark, letter or points.
 *
 * Gated on `grades.finalize`, held by both Instructor and Department Head (§3.2) — the
 * distinguishing authority is the Department assignment, checked here, never the permission
 * alone.
 */
class DepartmentHeadReviewController
{
    use BuildsSheetPayload;
    use ScopesToDepartmentHead;

    /**
     * The Department Head's review queue.
     *
     * Every Submitted Grade Sheet routed to the acting Head's Department(s) — including, for a
     * Head assigned university-wide, the department-less Preparatory Offerings a
     * Department-scoped Head never sees ({@see ScopesToDepartmentHead}). Oldest signature
     * first.
     *
     * @response 200 {"data": [{"id": 4, "course_offering_id": 3, "kind": "Initial", "signed_at": "2026-08-10T09:00:00+00:00", "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}}]}
     */
    public function queue(
        Request $request,
        DepartmentHeadSheetQueue $queue,
        Directory $directory,
    ): JsonResponse {
        // The Department scope — including the university-wide Head's reach over the
        // department-less Preparatory Sheets — is resolved once, in the seam the Sheets Awaiting
        // Review Dashboard Card reads too (phase 4.5, ticket 05), so a Card can never count
        // something this queue would not show.
        ['sheets' => $mine, 'offerings' => $offeringsById, 'courses' => $coursesById] =
            $queue->forHead($this->actingUserId($request));

        if ($mine->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $instructors = $directory->findMany(array_values(array_unique(array_map(
            fn (GradeSheet $sheet): int => $offeringsById[(int) $sheet->course_offering_id]->instructorId,
            $mine->all(),
        ))));

        $rows = $mine->map(function (GradeSheet $sheet) use ($offeringsById, $coursesById, $instructors): array {
            $offering = $offeringsById[(int) $sheet->course_offering_id];
            $course = $coursesById[$offering->courseId] ?? null;

            return [
                'id' => (int) $sheet->getKey(),
                'course_offering_id' => $offering->id,
                'kind' => $sheet->kind->value,
                'signed_at' => $sheet->signed_at?->toIso8601String(),
                'course' => $course?->toArray(),
                'instructor' => ($instructors[$offering->instructorId] ?? null)?->toArray(),
            ];
        })->values()->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * Open one Sheet for review.
     *
     * Every mark, letter and Incomplete on the round, the Student identity per line, and the
     * Offering's grade distribution — a view, not a report (Phase 7 owns reporting). Scoped
     * exactly as the queue: a Sheet outside the acting Head's Department is a 403, regardless
     * of its status.
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Submitted", "signed_at": "2026-08-10T09:00:00+00:00", "reviewed_at": null, "review_note": null}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "lines": [{"enrollment_id": 5, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3}, "grade_history": [{"mark": 71, "letter_grade": "C", "gpa_points": 2, "finalized_at": "2026-06-01T09:00:00+00:00", "superseded": true}], "incomplete": null}, {"enrollment_id": 6, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 8, "university_id": "2026-MSC-CE-0002", "name": "Omar Student"}, "grade": null, "grade_history": [], "incomplete": {"completion_percentage": 80, "reason": "Hospitalised before the final project was due."}}], "distribution": [{"letter": "A+", "count": 0}]}}
     * @response 403 scenario="not this sheet's department head" {"message": "This grade sheet does not belong to your department."}
     */
    public function show(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        StudentDirectory $students,
        Directory $directory,
    ): JsonResponse {
        $offering = $this->authorizeReview($request, $gradeSheet, $enrollments, $courses, $heads);
        $roster = $this->rosterFor($gradeSheet, $offering, $enrollments);

        return response()->json([
            'data' => [
                'sheet' => $this->sheetArray($gradeSheet),
                'course' => $courses->find($offering->courseId)?->toArray(),
                'lines' => $this->lineArrays($gradeSheet, $roster, $students, $directory),
                'distribution' => $this->distributionArray($gradeSheet),
            ],
        ]);
    }

    /**
     * Finalize the Sheet.
     *
     * Submitted → Finalized (else a 409): the round's Grades become part of the academic
     * record and the round is permanently closed (ADR-0023). In the same transaction, for each
     * Grade, `earned` — "this Attempt reached C+ and earned its credit hours" — is written onto
     * its Enrollment through `Enrollment\Public` (ADR-0022): a plain
     * {@see EnrollmentRecords::recordOutcome()} normally, or, when the Grade resolves an
     * outstanding Incomplete (this round is an `IncompleteResolution`, ticket 05),
     * {@see EnrollmentRecords::resolveIncomplete()} instead — which also restores the Course
     * Status the Enrollment held before it became Incomplete. On an **Initial** round, every
     * line declared Incomplete instead of graded (no Grade row) becomes one through
     * {@see EnrollmentRecords::markIncomplete()}, in the same transaction — no grade, and no
     * Incomplete, enters the record on one signature. After commit, `GradeFinalized` fires for
     * its side effects only: one notification per graded Student (their letter and whether it
     * fell below C+), and one per newly-declared Incomplete (the reason and its derived
     * deadline).
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Finalized", "signed_at": "2026-08-10T09:00:00+00:00", "reviewed_at": "2026-08-11T09:00:00+00:00", "review_note": null}}}
     * @response 403 scenario="not this sheet's department head" {"message": "This grade sheet does not belong to your department."}
     * @response 409 scenario="not submitted" {"message": "A grade sheet cannot move from Draft to Finalized."}
     */
    public function finalize(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        AuditLogger $audit,
        StudentGuard $studentGuard,
    ): JsonResponse {
        $this->authorizeReview($request, $gradeSheet, $enrollments, $courses, $heads);

        $threshold = (float) config('grading.min_grade_per_course');
        $actorId = $this->actingUserId($request);
        $auditChanges = [];
        $affectedEnrollments = $enrollments->findManyEnrollments(
            $gradeSheet->grades->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->all(),
        );
        $affectedStudentIds = collect($affectedEnrollments)
            ->pluck('studentId')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();

        DB::transaction(function () use ($gradeSheet, $enrollments, $threshold, $actorId, &$auditChanges, $audit, $studentGuard, $affectedStudentIds): void {
            // Identity-first locks serialize Grade finalization with credit approval and
            // final termination for every Student affected by this Sheet.
            foreach ($affectedStudentIds as $studentId) {
                abort_if($studentGuard->lock($studentId) === null, 409);
            }
            $before = $gradeSheet->kind === GradeSheetKind::Correction
                ? CurrentGradeLookup::forEnrollments(
                    $gradeSheet->grades->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->all(),
                )
                : [];

            $gradeSheet->finalize($actorId);

            $resolving = Incomplete::query()
                ->where('resolution_grade_sheet_id', $gradeSheet->getKey())
                ->get()
                ->keyBy(fn (Incomplete $incomplete): int => (int) $incomplete->enrollment_id);

            /** @var Grade $grade */
            foreach ($gradeSheet->grades as $grade) {
                $earned = (float) $grade->gpa_points >= $threshold;
                $incomplete = $resolving->get((int) $grade->enrollment_id);

                if ($incomplete !== null) {
                    $enrollments->resolveIncomplete((int) $grade->enrollment_id, $earned);
                    $incomplete->update(['resolved_grade_id' => $grade->getKey()]);
                } else {
                    $enrollments->recordOutcome((int) $grade->enrollment_id, $earned);
                }

                if ($gradeSheet->kind === GradeSheetKind::Correction) {
                    $prior = $before[(int) $grade->enrollment_id] ?? null;
                    if ($prior !== null) {
                        $auditChanges[] = [
                            'enrollment_id' => (int) $grade->enrollment_id,
                            'from' => $prior->toLineArray(),
                            'to' => $grade->toLineArray(),
                        ];
                    }
                }
            }

            if ($gradeSheet->kind === GradeSheetKind::Initial) {
                $declared = Incomplete::query()->where('declared_on_grade_sheet_id', $gradeSheet->getKey())->get();
                foreach ($declared as $incomplete) {
                    $enrollments->markIncomplete((int) $incomplete->enrollment_id);
                }
            }

            if ($gradeSheet->kind === GradeSheetKind::Correction && $auditChanges !== []) {
                $audit->record(
                    action: 'grade.correction_finalized',
                    actorUserId: $actorId,
                    metadata: [
                        'grade_sheet_id' => (int) $gradeSheet->getKey(),
                        'course_offering_id' => (int) $gradeSheet->course_offering_id,
                        'corrected_by_user_id' => (int) $gradeSheet->signed_by,
                        'reviewed_by_user_id' => $actorId,
                        'changes' => $auditChanges,
                    ],
                    subjectType: 'grade_sheet',
                    subjectId: (string) $gradeSheet->getKey(),
                );
            }
        });

        $finalizedEnrollmentIds = $gradeSheet->grades->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->all();
        Event::dispatch(new GradeFinalized((int) $gradeSheet->getKey(), $finalizedEnrollmentIds));
        Event::dispatch(new GradeOutcomesFinalized((int) $gradeSheet->getKey(), $finalizedEnrollmentIds));

        return response()->json(['data' => ['sheet' => $this->sheetArray($gradeSheet)]]);
    }

    /**
     * Return the Sheet with a note.
     *
     * Submitted → Returned (else a 409): the round becomes editable and re-signable again, and
     * the note reaches the Instructor as a notification. A note is required — a bilingual 422
     * when it is missing.
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     *
     * @bodyParam note string required Why the sheet is being returned. Example: The distribution looks implausible — please recheck line 5.
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Returned", "signed_at": "2026-08-10T09:00:00+00:00", "reviewed_at": "2026-08-11T09:00:00+00:00", "review_note": "Please recheck line 3."}}}
     * @response 403 scenario="not this sheet's department head" {"message": "This grade sheet does not belong to your department."}
     * @response 409 scenario="not submitted" {"message": "A grade sheet cannot move from Draft to Returned."}
     * @response 422 scenario="missing note" {"message": "A note is required when returning a grade sheet to its instructor.", "errors": {"note": ["A note is required when returning a grade sheet to its instructor."]}}
     */
    public function returnSheet(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        Notifier $notifier,
    ): JsonResponse {
        $offering = $this->authorizeReview($request, $gradeSheet, $enrollments, $courses, $heads);

        $validated = $request->validate(
            ['note' => ['required', 'string', 'max:2000']],
            ['note.required' => __('grading.sheet.return_note_required')],
        );

        $gradeSheet->returnToInstructor($this->actingUserId($request), $validated['note']);

        $notifier->notify($offering->instructorId, NotificationType::GradeSheetReturned, [
            'grade_sheet_id' => $gradeSheet->getKey(),
            'note' => $validated['note'],
        ]);

        return response()->json(['data' => ['sheet' => $this->sheetArray($gradeSheet)]]);
    }

    /**
     * Resolve the Sheet's Offering and enforce the Department scope guard in one place — every
     * action in this controller checks the same thing before doing anything else.
     */
    private function authorizeReview(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
    ): OfferingSummary {
        $offering = $enrollments->findOffering($gradeSheet->course_offering_id);
        abort_if($offering === null, 404, __('grading.offering.missing'));

        $departmentId = $this->departmentIdFor($offering, $courses);
        $this->authorizeDepartmentHead($this->actingUserId($request), $departmentId, $heads);

        return $offering;
    }

    private function actingUserId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
