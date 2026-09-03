<?php

namespace Modules\Grading\Internal\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Http\Concerns\AuthorizesGradeEntry;
use Modules\Grading\Internal\Http\Resources\DashboardCardResource;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Support\AcademicRecordCardBuilder;
use Modules\Grading\Internal\Support\DepartmentHeadSheetQueue;
use Modules\Grading\Internal\Support\GradeSheetsCardBuilder;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Grading\Internal\Support\OutstandingIncompleteQueue;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * @group Grading
 *
 * Grading's Dashboard Cards (phase 4.5, ADR-0024) — the small, scoped summaries the
 * authenticated entry screen composes. Each is **read-only**, lives in the module that owns the
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
    use AuthorizesGradeEntry;

    /** The three-row Card convention the shared `<DashboardCard>` is built around. */
    private const ROW_LIMIT = 3;

    /**
     * My Grade Sheets Card.
     *
     * The acting user's teaching load for their current term and what it owes: the Offerings, how
     * many of their Grade Sheets are **not yet Finalized**, and which are past the Semester's
     * Grade-Submission Deadline with nothing finalized. `offerings` carries the first few rows so
     * the Card links into each Offering's Sheet, while the counts are over the whole term.
     *
     * The overdue flag is a **nudge, not a gate**: nothing here is blocked, changed or
     * auto-actioned by it — this is a second surface for the flag phase 4 ticket 09 already
     * raises, read from the same derived predicate.
     *
     * Whose Offerings, exactly, is the rule the Grade Sheets screen already applies: an
     * Instructor's own, or — for a **central-entry** user, `grades.enter` without
     * `grades.finalize`, which by §3.2 is Student Affairs — every Offering, because grades
     * arriving on paper belong to Offerings the records office does not teach. `meta.scope` says
     * which list came back (`own` / `central`), exactly as that screen's endpoint does.
     *
     * The term is derived from the Offerings themselves — the latest-starting Semester the user
     * teaches in that has already begun — because grades are keyed in *after* a term ends, when
     * no Semester is running and the marks are still owed. A user who teaches nothing, or only in
     * terms still ahead, gets a null Semester and the Card's empty state rather than an error.
     *
     * Gated on `grades.enter` (§3.2 — Instructor / Student Affairs).
     *
     * The empty case is described above rather than given its own `@response` scenario: a second 200
     * whose body is a *different shape* — nulls and empty lists where the first has objects — makes
     * Scribe emit a union response type, and the generated Orval client then cannot read either
     * branch.
     *
     * @response 200 {"data": {"semester": {"id": 2, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026"}, "grade_submission_deadline_at": "2027-01-10T00:00:00+00:00", "offering_count": 2, "unfinalized_count": 1, "overdue_count": 1, "offerings": [{"offering_id": 3, "sheet_status": "Draft", "is_overdue": true, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}}]}, "meta": {"scope": "own"}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function gradeSheets(
        Request $request,
        EnrollmentRecords $enrollments,
        GradeSheetsCardBuilder $card,
    ): DashboardCardResource {
        $central = $this->actingAsCentralEntry($request);
        $offerings = $central
            ? $enrollments->allOfferings()
            : $enrollments->offeringsTaughtBy($this->actingUserId($request));

        return new DashboardCardResource(
            $card->build($offerings),
            ['scope' => $central ? 'central' : 'own'],
        );
    }

    /**
     * Sheets Awaiting Review Card.
     *
     * The Department Head's review queue at a glance: how many Submitted Grade Sheets are waiting
     * on them and which Offerings the oldest few belong to, linking into the review screen — so an
     * Instructor blocked on a Head becomes visible to that Head on sign-in.
     *
     * The Permission opens the Card; the **assignment** scopes its contents, exactly as the review
     * queue itself works. Both read the one seam ({@see DepartmentHeadSheetQueue}), so this Card
     * counts precisely what that queue would show — including the case that is easy to miss: a
     * Head assigned **university-wide** reaches the department-less Preparatory Sheets (ADR-0010)
     * and a Department-scoped Head does not. A Department with no Head assigned is on nobody's
     * Card, and that is not an error.
     *
     * Gated on `grades.finalize`, which Instructors hold too (§3.2) — an Instructor who heads no
     * Department therefore gets an empty queue rather than a 403, because the assignment, not the
     * Permission, is what fills it.
     *
     * The empty queue is described above rather than given its own `@response` scenario, for the
     * same reason as the Card above: an empty `sheets` list beside a populated one makes Scribe emit
     * a union response type the generated client cannot read.
     *
     * @response 200 {"data": {"pending_count": 2, "sheets": [{"id": 4, "course_offering_id": 3, "kind": "Initial", "signed_at": "2026-08-10T09:00:00+00:00", "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true, "department_id": 1}}]}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function sheetsAwaitingReview(Request $request, DepartmentHeadSheetQueue $queue): DashboardCardResource
    {
        ['sheets' => $mine, 'offerings' => $offerings, 'courses' => $courses] =
            $queue->forHead($this->actingUserId($request));

        $rows = $mine->take(self::ROW_LIMIT)->map(function (GradeSheet $sheet) use ($offerings, $courses): array {
            $offering = $offerings[(int) $sheet->course_offering_id];

            return [
                'id' => (int) $sheet->getKey(),
                'course_offering_id' => $offering->id,
                'kind' => $sheet->kind->value,
                'signed_at' => $sheet->signed_at?->toIso8601String(),
                'course' => ($courses[$offering->courseId] ?? null)?->toArray(),
            ];
        })->values()->all();

        return new DashboardCardResource([
            'pending_count' => $mine->count(),
            'sheets' => $rows,
        ]);
    }

    /**
     * Outstanding Incompletes Card.
     *
     * How many Incompletes the acting Instructor has declared that are still waiting on the
     * outstanding mark, and **when the nearest one is due** — the Card that earns its place by
     * preventing real harm, since an Incomplete left unresolved past its deadline is converted to
     * a Fail Withdrawal by the one state-changing scheduled command in the system. Surfacing the
     * nearest deadline on sign-in gives the Instructor the chance to resolve it first.
     *
     * The set is the same one the resolution screen lists — both read
     * {@see OutstandingIncompleteQueue} — so the Card can never count something the screen behind
     * it does not show.
     *
     * The deadline is **derived, never stored**: the following Semester's start plus the
     * configured offset, read through {@see IncompleteDeadlineResolver} on every request
     * (ADR-0018), so correcting a Semester's `starts_at` moves every outstanding deadline with it
     * and this Card with them. The resolver is asked once per Enrollment Semester rather than once
     * per Incomplete, since a term's answer is the same for every Incomplete declared in it.
     *
     * `without_deadline_count` is the case the conversion command already handles explicitly and
     * this Card must not paper over: an Incomplete whose following Semester **does not exist yet**
     * has no computable deadline. It is counted and reported as having none, never given a
     * fabricated one, and it never errors the Card — an admin's omission in the Calendar does not
     * hide an Instructor's work from them.
     *
     * Gated on `grades.enter` (§3.2 — Instructor / Student Affairs). A central-entry user teaches
     * no Offerings of their own, so they get the empty state, exactly as the resolution screen
     * already shows them.
     *
     * @response 200 {"data": {"outstanding_count": 2, "nearest_deadline": "2026-11-03T00:00:00+00:00", "without_deadline_count": 1}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function incompletes(
        Request $request,
        OutstandingIncompleteQueue $queue,
        IncompleteDeadlineResolver $deadlines,
    ): DashboardCardResource {
        ['incompletes' => $outstanding, 'enrollments' => $enrollmentsByIncomplete] =
            $queue->forInstructor($this->actingUserId($request));

        /** @var array<int, ?CarbonImmutable> $deadlineBySemester */
        $deadlineBySemester = [];
        $nearest = null;
        $withoutDeadline = 0;

        foreach ($outstanding as $incomplete) {
            $semesterId = $enrollmentsByIncomplete[(int) $incomplete->getKey()]->semesterId;

            if (! array_key_exists($semesterId, $deadlineBySemester)) {
                $deadlineBySemester[$semesterId] = $deadlines->forEnrollmentSemester($semesterId);
            }

            $deadline = $deadlineBySemester[$semesterId];

            if ($deadline === null) {
                $withoutDeadline++;
            } elseif ($nearest === null || $deadline->lessThan($nearest)) {
                $nearest = $deadline;
            }
        }

        return new DashboardCardResource([
            'outstanding_count' => count($outstanding),
            'nearest_deadline' => $nearest?->toIso8601String(),
            'without_deadline_count' => $withoutDeadline,
        ]);
    }

    /**
     * My Academic Record Card.
     *
     * The three numbers a Student's Dashboard shows: the CGPA, the latest Semester's GPA, and
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
    ): DashboardCardResource {
        $student = $this->actingStudent($request, $students);

        return new DashboardCardResource($card->forStudent($student->studentId));
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
