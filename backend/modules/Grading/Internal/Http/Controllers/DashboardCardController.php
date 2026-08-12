<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Grading\Internal\Support\AcademicRecordCardBuilder;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * @group Grading
 *
 * Grading's Dashboard Cards (phase 4.5, ADR-0024) — the small, scoped summaries the
 * authenticated landing page composes. Each is **read-only**, lives in the module that owns the
 * data, is **independently authorized** by its own route gate, and returns typed domain values
 * (numbers, ids, enum values) rather than pre-rendered display strings: the SPA renders the Card
 * bilingually from the payload, exactly as Notifications does.
 *
 * A Card introduces no domain logic of its own. Every figure here is read from the derivation
 * that already owns it — never recomputed and never cached (ADR-0020, the derived-on-demand
 * house rule) — so a Card can never disagree with the full surface it links into.
 */
class DashboardCardController
{
    /**
     * My Academic Record Card.
     *
     * The three numbers a Student's landing page shows: the CGPA, the latest Semester's GPA, and
     * the credit hours earned so far. `cgpa` and `latest_semester_gpa` are **null — never
     * 0.00** — before any CGPA-counting Grade has been finalized, so the SPA renders a dash and
     * "not yet calculated" is never mistaken for "you scored zero".
     *
     * Gated on `grades.view_own` (§3.2, Student only) and scoped to the acting user's own Student
     * anchor: there is no id parameter, so another Student's record is unreachable from it.
     *
     * @response 200 {"data": {"cgpa": 3.42, "latest_semester": {"id": 3, "term": "Fall", "year": 2025, "name_en": "Fall 2025", "name_ar": "خريف 2025"}, "latest_semester_gpa": 3.67, "earned_credit_hours": 12}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record."}
     */
    public function academicRecord(
        Request $request,
        StudentDirectory $students,
        AcademicRecordCardBuilder $card,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        return response()->json(['data' => $card->forStudent($student->studentId)]);
    }

    /**
     * The acting Student's anchor, or a 403 when the acting user holds no Student record — R02
     * holds `grades.view_own` and always has one, but a System/Super Administrator holds every
     * permission too (ADR-0008) and has no Student anchor to read. That is exactly why a persona
     * Card gates on the **Role** in the SPA (ADR-0024): the Permission alone would render a
     * broken Card for an administrator, and the server refusing it is the authority behind that
     * UX rule (the {@see MyGradesController} precedent).
     */
    private function actingStudent(Request $request, StudentDirectory $students): StudentSummary
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('grading.student.missing'));

        return $student;
    }
}
