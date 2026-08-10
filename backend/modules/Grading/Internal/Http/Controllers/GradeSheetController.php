<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Http\Concerns\AuthorizesGradeEntry;
use Modules\Grading\Internal\Http\Concerns\BuildsSheetPayload;
use Modules\Grading\Internal\Http\Concerns\ScopesToDepartmentHead;
use Modules\Grading\Internal\Models\CorrectionLine;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Grading
 *
 * The **Grade Sheet** — the Instructor's mark-entry surface (ADR-0023, ticket 01). A Sheet is
 * one submission round for one Course Offering, created **lazily** the first time it is opened
 * rather than eagerly at semester end, and it lists one line per **gradable** Enrollment:
 * Audit and Withdrawn Enrollments are on the roster but never lines, so an Instructor is never
 * asked for a mark that cannot exist.
 *
 * Entry is gated on `grades.enter`, but the permission is not authority over a *particular*
 * Sheet — that comes from the Offering assignment, or from being a central-entry user (ticket
 * 09: `grades.enter` without `grades.finalize`, which by §3.2 is Student Affairs), both checked
 * by {@see AuthorizesGradeEntry}. Every central write to someone else's Sheet is audited; an
 * Instructor's own is not. The §3.2 matrix itself is untouched throughout.
 *
 * One Mark, 0–100 with two decimals, is entered per line, and the Letter Grade and GPA points
 * are **derived from the ADR-0021 scale and frozen onto the Grade** — so a later change to the
 * scale never rewrites a grade already given, and there is no letter override to make
 * `letter == scale(mark)` anything but an invariant. Each mark saves on its own, not as a
 * batch (the Phase-2 per-step-save precedent), so a long roster survives a closed tab.
 *
 * Nothing here signs, reviews or finalizes: ticket 03 builds the Instructor signature and the
 * Department-Head review that turn a Draft round into part of the academic record, and no
 * Student can see anything until it does.
 */
class GradeSheetController
{
    use AuthorizesGradeEntry;
    use BuildsSheetPayload;
    use ScopesToDepartmentHead;

    /**
     * Offerings to grade.
     *
     * Every Course Offering the acting user teaches, across all Semesters, each with its
     * resolved Course and Semester, the Instructor it is assigned to, the number of gradable
     * lines its Sheet would carry, and the Initial round's state — `null` while unstarted, else
     * Draft/Submitted/Returned/Finalized (ticket 03) — the entry point to each Offering's Grade
     * Sheet. A user who teaches nothing gets an empty list rather than an error.
     *
     * For a **central-entry** user — `grades.enter` without `grades.finalize`, which by §3.2 is
     * Student Affairs — this is instead the picker over **every** Offering (ticket 09), because
     * grades arriving on paper belong to Offerings the records office does not teach. Which of
     * the two lists was returned is stated in `meta.scope` (`own` / `central`), so the SPA never
     * has to re-derive the rule the server applied.
     *
     * @response 200 {"data": [{"offering_id": 3, "gradable_count": 12, "sheet_status": "Draft", "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "semester": {"id": 2, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}}], "meta": {"scope": "own"}}
     */
    public function offerings(
        Request $request,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        AcademicCalendar $calendar,
        Directory $directory,
    ): JsonResponse {
        $central = $this->actingAsCentralEntry($request);
        $offerings = $central
            ? $enrollments->allOfferings()
            : $enrollments->offeringsTaughtBy($this->actingUserId($request));

        // Batched reads for the whole list, never one per row: the Courses, Semesters and
        // Instructors through their owning modules' facades (ADR-0012), the roster sizes through
        // the Enrollment facade, and each Offering's Initial-round state from Grading's own table.
        $resolved = $courses->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->courseId,
            $offerings,
        ))));
        $semesters = $calendar->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->semesterId,
            $offerings,
        ))));
        $instructors = $directory->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->instructorId,
            $offerings,
        ))));
        $offeringIds = array_map(fn (OfferingSummary $offering): int => $offering->id, $offerings);
        $counts = $enrollments->gradableCountsFor($offeringIds);
        $sheets = $offeringIds === [] ? collect() : GradeSheet::query()
            ->where('kind', GradeSheetKind::Initial)
            ->whereIn('course_offering_id', $offeringIds)
            ->get()
            ->keyBy(fn (GradeSheet $sheet): int => (int) $sheet->course_offering_id);

        $rows = array_map(function (OfferingSummary $offering) use ($resolved, $semesters, $instructors, $counts, $sheets): array {
            $semester = $semesters[$offering->semesterId] ?? null;
            $instructor = $instructors[$offering->instructorId] ?? null;

            return [
                'offering_id' => $offering->id,
                'gradable_count' => $counts[$offering->id] ?? 0,
                'sheet_status' => $sheets->get($offering->id)?->status->value,
                'course' => ($resolved[$offering->courseId] ?? null)?->toArray(),
                'semester' => $semester === null ? null : [
                    'id' => $semester->id,
                    'name_en' => $semester->nameEn,
                    'name_ar' => $semester->nameAr,
                ],
                'instructor' => $instructor?->toArray(),
            ];
        }, $offerings);

        return response()->json([
            'data' => $rows,
            'meta' => ['scope' => $central ? 'central' : 'own'],
        ]);
    }

    /**
     * Open an Offering's Grade Sheet.
     *
     * The assigned Instructor's Sheet for one Offering, carrying one line per **gradable**
     * Enrollment. The Initial round is created **lazily** on this first open (ADR-0023) —
     * never eagerly at semester end — and re-opening returns the same round rather than
     * starting another. Each line carries the Student's University ID and name, resolved
     * through Identity\Public, and the Grade already recorded for it, or `null` while the line
     * is still blank. Audit and Withdrawn Enrollments are omitted.
     * Authorised by assignment: an Instructor opening an Offering they do not teach gets a 403.
     * A central-entry user (ticket 09) reaches any Offering's Sheet here, and opening one — a
     * read — is not audited; their line writes are.
     *
     * @urlParam offering_id integer required The ID of the course offering. Example: 3
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Returned", "signed_at": "2026-08-10T09:00:00+00:00", "reviewed_at": "2026-08-11T09:00:00+00:00", "review_note": "Please recheck line 3."}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "lines": [{"enrollment_id": 5, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3}, "grade_history": [{"mark": 71, "letter_grade": "C", "gpa_points": 2.0, "finalized_at": "2026-06-01T09:00:00+00:00", "superseded": true}], "incomplete": null}, {"enrollment_id": 6, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 8, "university_id": "2026-MSC-CE-0002", "name": "Omar Student"}, "grade": null, "grade_history": [], "incomplete": {"completion_percentage": 80, "reason": "Hospitalised before the final project was due."}}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 404 scenario="no such offering" {"message": "That course offering no longer exists."}
     */
    public function sheet(
        Request $request,
        int $offering_id,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        StudentDirectory $students,
        Directory $directory,
        AuditLogger $audit,
    ): JsonResponse {
        $offering = $this->authorizeOffering($offering_id, $request, $enrollments);

        // Re-opening is a pure read, but the *first* open writes the round into existence, so a
        // central-entry user starting someone else's Sheet is audited like any other write of
        // theirs — the Instructor is entitled to know their round was opened for them.
        $existing = GradeSheet::query()
            ->where('course_offering_id', $offering->id)
            ->where('kind', GradeSheetKind::Initial)
            ->exists();
        $sheet = $this->openInitialSheet($offering);

        if (! $existing) {
            $this->auditCentralEntry(
                actorUserId: $this->actingUserId($request),
                offering: $offering,
                audit: $audit,
                action: 'grade.sheet_opened_centrally',
                gradeSheetId: (int) $sheet->getKey(),
            );
        }

        $roster = $enrollments->gradableEnrollmentsOf($offering->id);

        return response()->json([
            'data' => [
                'sheet' => $this->sheetArray($sheet),
                'course' => $courses->find($offering->courseId)?->toArray(),
                'lines' => $this->lineArrays($sheet, $roster, $students, $directory),
            ],
        ]);
    }

    /**
     * Open one Correction round.
     *
     * A previously-opened Correction round, read by its own Sheet id so the SPA can return to
     * it directly. The round contains only the selected lines persisted on it at creation time;
     * the original finalized Sheet it corrects is untouched. Authorised by the same Offering
     * assignment as every other Instructor Sheet.
     *
     * @urlParam grade_sheet_id integer required The ID of the correction round. Example: 7
     *
     * @response 200 {"data": {"sheet": {"id": 7, "course_offering_id": 3, "kind": "Correction", "status": "Draft", "signed_at": null, "reviewed_at": null, "review_note": null}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "lines": [{"enrollment_id": 5, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": null, "grade_history": [{"mark": 82.5, "letter_grade": "B", "gpa_points": 3.0, "finalized_at": "2026-08-11T09:00:00+00:00", "superseded": true}], "incomplete": null}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     */
    public function correction(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        StudentDirectory $students,
        Directory $directory,
    ): JsonResponse {
        abort_if($gradeSheet->kind !== GradeSheetKind::Correction, 404);

        $offering = $this->authorizeOffering($gradeSheet->course_offering_id, $request, $enrollments);
        $roster = $this->rosterFor($gradeSheet, $offering, $enrollments);

        return response()->json([
            'data' => [
                'sheet' => $this->sheetArray($gradeSheet),
                'course' => $courses->find($offering->courseId)?->toArray(),
                'lines' => $this->lineArrays($gradeSheet, $roster, $students, $directory),
            ],
        ]);
    }

    /**
     * Open a Correction round against a finalized Sheet.
     *
     * Creates a fresh `Correction` round for a Sheet already in `Finalized`, containing only
     * the selected lines (ADR-0023): the original round is untouched and remains finalized
     * throughout. The returned round is then edited, signed and Department-Head-reviewed
     * through the existing Sheet endpoints. Authorised by the Offering assignment — only the
     * Instructor who teaches this Offering may start a correction on it. Central entry (ticket
     * 09) does **not** reach here: keying marks into a Draft round is data entry, reopening the
     * finalized record is the Instructor's own judgement. Once they have started one, its lines
     * are an ordinary Draft Sheet that central entry may key like any other.
     *
     * @urlParam grade_sheet_id integer required The ID of the finalized sheet being corrected. Example: 1
     *
     * @bodyParam enrollment_ids integer[] required The enrollment ids to correct from that finalized sheet. Example: [5]
     *
     * @response 201 {"data": {"sheet": {"id": 7, "course_offering_id": 3, "kind": "Correction", "status": "Draft", "signed_at": null, "reviewed_at": null, "review_note": null}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "lines": [{"enrollment_id": 5, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": null, "grade_history": [{"mark": 82.5, "letter_grade": "B", "gpa_points": 3.0, "finalized_at": "2026-08-11T09:00:00+00:00", "superseded": true}], "incomplete": null}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 409 scenario="sheet not finalized" {"message": "Only a finalized grade sheet can start a correction round."}
     * @response 422 scenario="selected line not on that finalized sheet" {"message": "A correction round can only include lines from that finalized grade sheet.", "errors": {"enrollment_ids": ["A correction round can only include lines from that finalized grade sheet."]}}
     */
    public function createCorrection(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        StudentDirectory $students,
        Directory $directory,
    ): JsonResponse {
        $offering = $this->authorizeOwnOffering($gradeSheet->course_offering_id, $request, $enrollments);

        if ($gradeSheet->status !== GradeSheetStatus::Finalized) {
            throw new ConflictHttpException(__('grading.sheet.correction_requires_finalized'));
        }

        $validated = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct'],
        ]);

        $selectedIds = array_values(array_map('intval', $validated['enrollment_ids']));
        $allowedIds = $gradeSheet->grades()->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->all();

        if (array_values(array_intersect($selectedIds, $allowedIds)) !== $selectedIds) {
            throw ValidationException::withMessages([
                'enrollment_ids' => [__('grading.sheet.correction_lines_invalid')],
            ]);
        }

        $correction = $this->openCorrectionSheet($offering, $selectedIds);
        $roster = $this->rosterFor($correction, $offering, $enrollments);

        return response()->json([
            'data' => [
                'sheet' => $this->sheetArray($correction),
                'course' => $courses->find($offering->courseId)?->toArray(),
                'lines' => $this->lineArrays($correction, $roster, $students, $directory),
            ],
        ], 201);
    }

    /**
     * Save one Grade Sheet line.
     *
     * Records the final percentage for a single line and returns the Letter Grade and GPA
     * points the ADR-0021 scale derives from it, **frozen** onto the Grade so a later scale
     * change never rewrites it. One line at a time, not a batch (the Phase-2 per-step-save
     * precedent); re-saving a line corrects it rather than adding a second Grade. A mark
     * outside 0–100 is a 422 naming the field, and an Enrollment that is not a line of this
     * Sheet is a 404. Authorised by assignment — the Offering's Instructor, or a central-entry
     * user keying grades in for someone else's Offering (ticket 09), whose write is recorded in
     * the Audit Log; anyone else is a 403.
     *
     * On an **Initial** round, a line may be marked Incomplete instead — send
     * `completion_percentage` and `reason` in place of `mark` (ticket 05, SRS §4.7.3.1); a
     * figure below the configured threshold is a 422 naming the field. Entering one supersedes
     * the other on the same line: a mark clears an earlier Incomplete declaration and an
     * Incomplete declaration clears an earlier mark.
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     * @urlParam enrollment_id integer required The ID of the enrollment being marked. Example: 5
     *
     * @bodyParam mark number The final percentage, 0–100 with at most two decimals. Required unless declaring an Incomplete. Example: 82.5
     * @bodyParam completion_percentage integer The coursework percentage completed, 0–100. Declares an Incomplete in place of a mark; requires `reason`. Example: 80
     * @bodyParam reason string The reason for the Incomplete. Required alongside `completion_percentage`. Example: Hospitalised before the final project was due.
     *
     * @response 200 scenario="a mark" {"data": {"enrollment_id": 5, "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3}, "incomplete": null}}
     * @response 200 scenario="an incomplete declaration" {"data": {"enrollment_id": 5, "grade": null, "incomplete": {"completion_percentage": 80, "reason": "Hospitalised before the final project was due."}}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 404 scenario="not a line of this sheet" {"message": "That enrollment is not a line of this grade sheet."}
     * @response 422 scenario="mark out of range" {"message": "A mark must be a percentage between 0 and 100.", "errors": {"mark": ["A mark must be a percentage between 0 and 100."]}}
     * @response 422 scenario="completion below threshold" {"message": "An Incomplete requires at least 70% of the coursework completed.", "errors": {"completion_percentage": ["An Incomplete requires at least 70% of the coursework completed."]}}
     */
    public function saveLine(
        Request $request,
        GradeSheet $gradeSheet,
        int $enrollment_id,
        EnrollmentRecords $enrollments,
        AuditLogger $audit,
    ): JsonResponse {
        $offering = $this->authorizeOffering($gradeSheet->course_offering_id, $request, $enrollments);

        // A Submitted Sheet is read-only to everyone including its Instructor, and a Finalized
        // one is closed forever (ADR-0023). Only Draft is reachable until ticket 03 builds the
        // signature loop, so this is a no-op guard today — encoded up front so the meaning is
        // fixed, exactly as Enrollment's active-roster scope encoded W before ticket 07.
        if (! $gradeSheet->acceptsMarks()) {
            throw new ConflictHttpException(__('grading.sheet.not_editable'));
        }

        $line = $this->requireLine($gradeSheet, $offering, $enrollment_id, $enrollments);

        if ($request->has('completion_percentage') || $request->has('reason')) {
            return $this->saveIncompleteDeclaration($request, $gradeSheet, $line, $offering, $audit);
        }

        return $this->saveMark($request, $gradeSheet, $line, $offering, $audit);
    }

    /** The ordinary mark-entry path — supersedes any earlier Incomplete declaration on the line. */
    private function saveMark(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentSummary $line,
        OfferingSummary $offering,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate(
            ['mark' => ['required', 'numeric', 'decimal:0,2', 'between:0,100']],
            [
                'mark.numeric' => __('grading.mark.out_of_range'),
                'mark.between' => __('grading.mark.out_of_range'),
                'mark.decimal' => __('grading.mark.too_precise'),
            ],
        );

        $grade = Grade::updateOrCreate(
            ['grade_sheet_id' => $gradeSheet->getKey(), 'enrollment_id' => $line->id],
            Grade::derivedFrom((float) $validated['mark']),
        );

        Incomplete::query()
            ->where('declared_on_grade_sheet_id', $gradeSheet->getKey())
            ->where('enrollment_id', $line->id)
            ->delete();

        $this->auditCentralEntry(
            actorUserId: $this->actingUserId($request),
            offering: $offering,
            audit: $audit,
            action: 'grade.entered_centrally',
            gradeSheetId: (int) $gradeSheet->getKey(),
            metadata: ['enrollment_id' => $line->id, 'mark' => (float) $grade->mark],
        );

        return response()->json([
            'data' => [
                'enrollment_id' => $line->id,
                'grade' => $grade->toLineArray(),
                'incomplete' => null,
            ],
        ]);
    }

    /**
     * The Incomplete-declaration path (ticket 05, SRS §4.7.3.1) — only meaningful on the
     * Offering's Initial round; a resolution round enters the outstanding mark instead. The
     * completion figure is an Instructor **attestation**, not a measurement (ADR-0019), refused
     * below the configured threshold. Declaring supersedes any earlier mark on the same line;
     * re-declaring corrects the percentage/reason rather than adding a second row (the Grade
     * `updateOrCreate` precedent). The Enrollment's Course Status does not change here — that
     * happens at the Sheet's finalize, in the same transaction as every other outcome
     * (ADR-0022/0023): no grade, and no Incomplete, enters the record on one signature.
     */
    private function saveIncompleteDeclaration(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentSummary $line,
        OfferingSummary $offering,
        AuditLogger $audit,
    ): JsonResponse {
        abort_if($gradeSheet->kind !== GradeSheetKind::Initial, 422, __('grading.incomplete.only_on_initial_sheet'));

        $threshold = (int) config('grading.incomplete_min_completion');

        $validated = $request->validate(
            [
                'completion_percentage' => ['required', 'integer', 'between:0,100'],
                'reason' => ['required', 'string', 'max:2000'],
            ],
            ['reason.required' => __('grading.incomplete.reason_required')],
        );

        if ($validated['completion_percentage'] < $threshold) {
            throw ValidationException::withMessages([
                'completion_percentage' => [__('grading.incomplete.completion_too_low', ['threshold' => $threshold])],
            ]);
        }

        $incomplete = Incomplete::updateOrCreate(
            ['declared_on_grade_sheet_id' => $gradeSheet->getKey(), 'enrollment_id' => $line->id],
            [
                'declared_by' => $this->actingUserId($request),
                'completion_percentage' => $validated['completion_percentage'],
                'reason' => $validated['reason'],
            ],
        );

        Grade::query()
            ->where('grade_sheet_id', $gradeSheet->getKey())
            ->where('enrollment_id', $line->id)
            ->delete();

        $this->auditCentralEntry(
            actorUserId: $this->actingUserId($request),
            offering: $offering,
            audit: $audit,
            action: 'grade.incomplete_declared_centrally',
            gradeSheetId: (int) $gradeSheet->getKey(),
            metadata: [
                'enrollment_id' => $line->id,
                'completion_percentage' => (int) $incomplete->completion_percentage,
            ],
        );

        return response()->json([
            'data' => [
                'enrollment_id' => $line->id,
                'grade' => null,
                'incomplete' => $incomplete->toLineArray(),
            ],
        ]);
    }

    /**
     * Sign the Sheet.
     *
     * The Instructor's signature — one act that sends the whole round to the Department Head
     * (SRS §4.7.1.4). Draft or Returned → Submitted (ADR-0023); any other current state is a
     * 409. Refused with a 422 naming how many lines are still blank — the UI already shows
     * that count from the Sheet's own lines, so this is a defence against a stale client, not
     * the Instructor's first signal. Once Submitted, the Sheet is read-only to everyone,
     * including its own Instructor, and every configured Department Head (or the
     * university-wide Head for a department-less Preparatory Offering) is notified.
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Submitted", "signed_at": "2026-08-10T09:00:00+00:00", "reviewed_at": null, "review_note": null}}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 409 scenario="already submitted" {"message": "A grade sheet cannot move from Submitted to Submitted."}
     * @response 422 scenario="blank lines remain" {"message": "This grade sheet still has 2 ungraded line(s) remaining.", "errors": {"lines": ["This grade sheet still has 2 ungraded line(s) remaining."]}}
     */
    public function sign(
        Request $request,
        GradeSheet $gradeSheet,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
        Notifier $notifier,
    ): JsonResponse {
        $offering = $this->authorizeOffering($gradeSheet->course_offering_id, $request, $enrollments);

        $blank = count($this->rosterFor($gradeSheet, $offering, $enrollments)) - $this->decidedCount($gradeSheet);
        if ($blank > 0) {
            throw ValidationException::withMessages([
                'lines' => [__('grading.sheet.blank_lines_remain', ['count' => $blank])],
            ]);
        }

        $gradeSheet->sign($this->actingUserId($request));

        $departmentId = $this->departmentIdFor($offering, $courses);
        foreach ($heads->headsForDepartment($departmentId) as $headUserId) {
            $notifier->notify($headUserId, NotificationType::GradeSheetSubmitted, [
                'course_offering_id' => $offering->id,
                'grade_sheet_id' => $gradeSheet->getKey(),
            ]);
        }

        return response()->json(['data' => ['sheet' => $this->sheetArray($gradeSheet)]]);
    }

    /**
     * The Offering's Initial round, created lazily on first open and returned unchanged on
     * every later one (ADR-0023). Later rounds — Incomplete resolutions and corrections — are
     * their own kinds and their own tickets, so "the Sheet" is unambiguous here.
     *
     * `firstOrCreate` is a read-then-write, so it is only atomic because the table carries a
     * partial unique index on the Initial round: on a losing race the insert raises a unique
     * violation, which Laravel catches and re-reads. Without that index two concurrent first
     * opens would each create a round and split the Offering's grades in two.
     */
    private function openInitialSheet(OfferingSummary $offering): GradeSheet
    {
        return GradeSheet::firstOrCreate(
            ['course_offering_id' => $offering->id, 'kind' => GradeSheetKind::Initial],
            ['status' => GradeSheetStatus::Draft],
        );
    }

    /**
     * A fresh Correction round for exactly the selected lines — a new round every time, never
     * reopening the finalized source Sheet (ADR-0023). The selected-line rows are written in
     * the same transaction as the round itself so a Correction can never exist with an empty or
     * partial roster.
     *
     * @param  list<int>  $enrollmentIds
     */
    private function openCorrectionSheet(OfferingSummary $offering, array $enrollmentIds): GradeSheet
    {
        return DB::transaction(function () use ($offering, $enrollmentIds): GradeSheet {
            $sheet = GradeSheet::create([
                'course_offering_id' => $offering->id,
                'kind' => GradeSheetKind::Correction,
                'status' => GradeSheetStatus::Draft,
            ]);

            CorrectionLine::insert(array_map(fn (int $enrollmentId): array => [
                'grade_sheet_id' => $sheet->getKey(),
                'enrollment_id' => $enrollmentId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $enrollmentIds));

            return $sheet;
        });
    }

    /**
     * The Enrollment being marked, or a 404 — checked against this Sheet's own kind-aware
     * roster ({@see self::rosterFor()}) on every save rather than trusted from the client, so a
     * stale Sheet — or a hostile request naming a stranger's Enrollment, or an Audit line —
     * cannot write a Grade (or an Incomplete declaration) that should not exist.
     */
    private function requireLine(GradeSheet $sheet, OfferingSummary $offering, int $enrollmentId, EnrollmentRecords $enrollments): EnrollmentSummary
    {
        foreach ($this->rosterFor($sheet, $offering, $enrollments) as $enrollment) {
            if ($enrollment->id === $enrollmentId) {
                return $enrollment;
            }
        }

        abort(404, __('grading.line.not_found'));
    }
}
