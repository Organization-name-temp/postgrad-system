<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Grading\Internal\Support\StudentGradesRecordBuilder;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * @group Grading
 *
 * My Grades (ADR-0020, ticket 04) — the Student's own academic record, closing SRS §4.7.2:
 * every Semester grouped chronologically, each with its own GPA beside its Courses, and the
 * CGPA prominently — the one number the whole degree turns on (spec §GPA and CGPA).
 *
 * Nothing is visible until the Department Head has finalized the Sheet
 * ({@see CurrentGradeLookup}): a Student never sees a provisional grade that later changes, so
 * only Enrollments with a **Finalized** Grade appear — **plus** any Incomplete (ticket 05,
 * SRS §4.7.3.2) with its derived deadline, and, once converted, the Fail Withdrawal itself,
 * neither of which ever carries a Grade. A superseded earlier Attempt of a retaken Course still
 * appears — its credit hours and letter are never hidden — but is flagged `superseded` and
 * excluded from every average it no longer counts toward
 * ({@see GpaCalculator::nonSuperseded()}), so the rule is legible rather than leaving the
 * numbers looking wrong. Two different electives never compete for the same row; both simply
 * appear (FR-REG-006.4).
 *
 * Gated on `grades.view_own` (§3.2, Student only) and scoped to the acting user's own Student
 * anchor — there is no id parameter, so another Student's record is unreachable from this
 * endpoint (the Advisor/Student-Affairs scoped read is ticket 08's own surface).
 */
class MyGradesController
{
    /**
     * My Grades.
     *
     * @response 200 {"data": {"cgpa": 3.42, "semesters": [{"semester": {"id": 2, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "gpa": 3.42, "courses": [{"enrollment_id": 5, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "credit_hours": 3, "course_status": "Regular", "grade": {"mark": 82.5, "letter_grade": "B", "gpa_points": 3.0}, "grade_history": [{"mark": 71, "letter_grade": "C", "gpa_points": 2.0, "finalized_at": "2026-06-01T09:00:00+00:00", "superseded": true}], "earned": true, "superseded": false, "must_retake": false, "incomplete_deadline": null}, {"enrollment_id": 9, "course": {"id": 2, "code": "DEP502", "name_en": "Distributed Systems", "name_ar": "الأنظمة الموزعة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}, "credit_hours": 3, "course_status": "Incomplete", "grade": null, "grade_history": [], "earned": null, "superseded": false, "must_retake": false, "incomplete_deadline": "2026-11-03T00:00:00+00:00"}]}]}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record."}
     */
    public function index(
        Request $request,
        StudentDirectory $students,
        StudentGradesRecordBuilder $records,
    ): JsonResponse {
        $student = $this->actingStudent($request, $students);

        return response()->json(['data' => $records->forStudent($student->studentId)]);
    }

    /**
     * The acting Student's anchor, or a 403 when the acting user holds no Student record — R02
     * holds `grades.view_own` and always has one, but a System/Super Administrator holds every
     * permission too (ADR-0008) and has no Student anchor to read, so this is reachable and not
     * merely defensive (the Enrollment `ResolvesStudent` precedent).
     */
    private function actingStudent(Request $request, StudentDirectory $students): StudentSummary
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('grading.student.missing'));

        return $student;
    }
}
