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
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;
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
 * Sheet — that comes from the Offering assignment, checked here (the Phase-2 interviewer and
 * Phase-3 attendance precedent for authorising by assignment). The §3.2 matrix is untouched.
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
    /**
     * My Offerings to grade.
     *
     * Every Course Offering the acting user teaches, across all Semesters, each with its
     * resolved Course and the number of gradable lines its Sheet would carry — the entry point
     * to each Offering's Grade Sheet. Scoped to the acting user by the Offering assignment: a
     * user who teaches nothing gets an empty list rather than an error. Each Offering's Sheet
     * *state* joins this list in ticket 03, with the signature loop that produces one.
     *
     * @response 200 {"data": [{"offering_id": 3, "gradable_count": 12, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}}]}
     */
    public function offerings(
        Request $request,
        EnrollmentRecords $enrollments,
        CourseCatalog $courses,
    ): JsonResponse {
        $offerings = $enrollments->offeringsTaughtBy($this->actingUserId($request));

        // Two batched reads for the whole list, never one per row: the Courses through the
        // Catalog facade, the roster sizes through the Enrollment facade (ADR-0012).
        $resolved = $courses->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->courseId,
            $offerings,
        ))));
        $counts = $enrollments->gradableCountsFor(array_map(
            fn (OfferingSummary $offering): int => $offering->id,
            $offerings,
        ));

        $rows = array_map(fn (OfferingSummary $offering): array => [
            'offering_id' => $offering->id,
            'gradable_count' => $counts[$offering->id] ?? 0,
            'course' => ($resolved[$offering->courseId] ?? null)?->toArray(),
        ], $offerings);

        return response()->json(['data' => $rows]);
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
     *
     * @urlParam offering_id integer required The ID of the course offering. Example: 3
     *
     * @response 200 {"data": {"sheet": {"id": 1, "course_offering_id": 3, "kind": "Initial", "status": "Draft"}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "lines": [{"enrollment_id": 5, "course_status": "Regular", "credit_hours": 3, "student": {"student_id": 7, "university_id": "2026-MSC-CE-0001", "name": "Sara Student"}, "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3}}]}}
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
    ): JsonResponse {
        $offering = $this->authorizeOffering($offering_id, $request, $enrollments);
        $sheet = $this->openInitialSheet($offering);
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
     * Save one Grade Sheet line.
     *
     * Records the final percentage for a single line and returns the Letter Grade and GPA
     * points the ADR-0021 scale derives from it, **frozen** onto the Grade so a later scale
     * change never rewrites it. One line at a time, not a batch (the Phase-2 per-step-save
     * precedent); re-saving a line corrects it rather than adding a second Grade. A mark
     * outside 0–100 is a 422 naming the field, and an Enrollment that is not a gradable line of
     * this Sheet's Offering is a 404. Authorised by assignment — only the Offering's Instructor
     * (else 403).
     *
     * @urlParam grade_sheet_id integer required The ID of the grade sheet. Example: 1
     * @urlParam enrollment_id integer required The ID of the gradable enrollment being marked. Example: 5
     *
     * @bodyParam mark number required The final percentage, 0–100 with at most two decimals. Example: 82.5
     *
     * @response 200 {"data": {"enrollment_id": 5, "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3}}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 404 scenario="not a gradable line" {"message": "That enrollment is not a gradable line on this grade sheet."}
     * @response 422 scenario="mark out of range" {"message": "A mark must be a percentage between 0 and 100.", "errors": {"mark": ["A mark must be a percentage between 0 and 100."]}}
     */
    public function saveLine(
        Request $request,
        GradeSheet $gradeSheet,
        int $enrollment_id,
        EnrollmentRecords $enrollments,
    ): JsonResponse {
        $offering = $this->authorizeOffering($gradeSheet->course_offering_id, $request, $enrollments);

        // A Submitted Sheet is read-only to everyone including its Instructor, and a Finalized
        // one is closed forever (ADR-0023). Only Draft is reachable until ticket 03 builds the
        // signature loop, so this is a no-op guard today — encoded up front so the meaning is
        // fixed, exactly as Enrollment's active-roster scope encoded W before ticket 07.
        if (! $gradeSheet->acceptsMarks()) {
            throw new ConflictHttpException(__('grading.sheet.not_editable'));
        }

        $line = $this->requireGradableLine($offering, $enrollment_id, $enrollments);

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

        return response()->json([
            'data' => [
                'enrollment_id' => $line->id,
                'grade' => $grade->toLineArray(),
            ],
        ]);
    }

    /**
     * The Offering the acting user is working, or an abort: a 404 when the reference dangles,
     * a 403 when they are not its assigned Instructor. Authority over a Sheet is the Offering
     * assignment, not the `grades.enter` permission the route already checked — Student
     * Affairs' central-entry path and the ADR-0015 administrative override are their own
     * tickets, so holding the permission without the assignment is forbidden here.
     */
    private function authorizeOffering(int $offeringId, Request $request, EnrollmentRecords $enrollments): OfferingSummary
    {
        $offering = $enrollments->findOffering($offeringId);
        abort_if($offering === null, 404, __('grading.offering.missing'));
        abort_if($offering->instructorId !== $this->actingUserId($request), 403, __('grading.offering.not_yours'));

        return $offering;
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
     * The gradable Enrollment being marked, or a 404. Membership is re-checked against
     * Enrollment's own judgement on every save rather than trusted from the client, so a stale
     * Sheet — or a hostile request naming a stranger's Enrollment, or an Audit line — cannot
     * write a Grade that should not exist.
     */
    private function requireGradableLine(OfferingSummary $offering, int $enrollmentId, EnrollmentRecords $enrollments): EnrollmentSummary
    {
        foreach ($enrollments->gradableEnrollmentsOf($offering->id) as $enrollment) {
            if ($enrollment->id === $enrollmentId) {
                return $enrollment;
            }
        }

        abort(404, __('grading.line.not_gradable'));
    }

    /**
     * One Sheet's wire shape — the round's identity and state, which the SPA renders as a
     * StatusChip.
     *
     * @return array{id: int, course_offering_id: int, kind: string, status: string}
     */
    private function sheetArray(GradeSheet $sheet): array
    {
        return [
            'id' => (int) $sheet->getKey(),
            'course_offering_id' => (int) $sheet->course_offering_id,
            'kind' => $sheet->kind->value,
            'status' => $sheet->status->value,
        ];
    }

    /**
     * The Sheet's lines: one per gradable Enrollment, carrying the Student's identity and the
     * Grade recorded so far (or `null` while blank). Student names and University IDs are
     * resolved through Identity\Public in one batched read each (ADR-0012), and the Grades in
     * one query keyed by Enrollment — so a roster of any size costs three reads, not 3N.
     *
     * @param  list<EnrollmentSummary>  $roster
     * @return list<array<string, mixed>>
     */
    private function lineArrays(GradeSheet $sheet, array $roster, StudentDirectory $students, Directory $directory): array
    {
        if ($roster === []) {
            return [];
        }

        $grades = Grade::query()
            ->where('grade_sheet_id', $sheet->getKey())
            ->get()
            ->keyBy(fn (Grade $grade): int => (int) $grade->enrollment_id);

        $summaries = $students->findManyByStudentId(array_values(array_unique(array_map(
            fn (EnrollmentSummary $enrollment): int => $enrollment->studentId,
            $roster,
        ))));
        $users = $directory->findMany(array_values(array_map(
            fn (StudentSummary $summary): int => $summary->userId,
            $summaries,
        )));

        return array_map(function (EnrollmentSummary $enrollment) use ($grades, $summaries, $users): array {
            $summary = $summaries[$enrollment->studentId] ?? null;

            return [
                'enrollment_id' => $enrollment->id,
                'course_status' => $enrollment->courseStatus,
                'credit_hours' => $enrollment->creditHours,
                'student' => [
                    'student_id' => $enrollment->studentId,
                    'university_id' => $summary?->universityId,
                    'name' => $this->studentName($enrollment, $summary, $users),
                ],
                'grade' => $grades->get($enrollment->id)?->toLineArray(),
            ];
        }, $roster);
    }

    /**
     * A line's Student display name: the Identity user's name, falling back to the University
     * ID and then to the anchor id — the Student anchor carries no name of its own (ADR-0013),
     * so the fallbacks keep a dangling reference legible instead of blank. The attendance
     * register's roster resolves a name the same way.
     *
     * @param  array<int, UserSummary>  $users
     */
    private function studentName(EnrollmentSummary $enrollment, ?StudentSummary $summary, array $users): string
    {
        if ($summary === null) {
            return "student #{$enrollment->studentId}";
        }

        return $users[$summary->userId]->name ?? $summary->universityId;
    }

    private function actingUserId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
