<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\StudentSummary;

/**
 * The graded-history acts a seeder performs on top of {@see SeedsAcademicWorld}: opening a
 * Grade Sheet round in the state a demo needs to rest in, and putting a Grade on it. Extracted
 * from `GradingSeeder` (phase 4) when `RoleAccountSeeder` (phase 4.5) needed exactly the same
 * three acts to give the canonical role accounts a real academic footprint — the same reason
 * `SeedsAcademicWorld` itself was extracted, so two demo worlds cannot drift in *how* they are
 * built while differing in what they contain.
 *
 * Every helper is **idempotent by natural key** — (Offering, kind) for a round, (round,
 * Enrollment) for a Grade — which is what makes `db:seed` safe to re-run.
 */
trait SeedsGradedHistory
{
    /**
     * One Grade Sheet round, keyed by (Offering, kind) so re-seeding neither duplicates a round
     * nor rewinds one a demo has since moved on. Its status and signature/review stamps are
     * written as the state the demo needs to *rest* in — the transitions themselves are
     * exercised by the API, which is what the headline smokes drive.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function sheet(CourseOffering $offering, GradeSheetKind $kind, GradeSheetStatus $status, array $attributes = []): GradeSheet
    {
        return GradeSheet::firstOrCreate(
            ['course_offering_id' => $offering->getKey(), 'kind' => $kind],
            ['status' => $status, ...$attributes],
        );
    }

    /**
     * A Finalized round — the state most seeded history rests in: signed `$signedWeeksAgo` weeks
     * ago and reviewed a week later, which is the only thing that differs between them and the
     * only ordering that matters. A Correction must be reviewed **after** the Initial round it
     * supersedes, because the current Grade is derived as the one on the most recently reviewed
     * Finalized Sheet (ADR-0023).
     */
    private function finalizedSheet(CourseOffering $offering, GradeSheetKind $kind, User $signedBy, User $reviewedBy, int $signedWeeksAgo): GradeSheet
    {
        return $this->sheet($offering, $kind, GradeSheetStatus::Finalized, [
            'signed_by' => $signedBy->getKey(),
            'signed_at' => Carbon::now()->subWeeks($signedWeeksAgo),
            'reviewed_by' => $reviewedBy->getKey(),
            'reviewed_at' => Carbon::now()->subWeeks($signedWeeksAgo - 1),
        ]);
    }

    /**
     * One Grade on a round, with its Letter Grade and points derived and frozen by the same
     * {@see Grade::derivedFrom()} the API uses, so a seeded grade can never disagree with an
     * entered one. On a **Finalized** round the ADR-0022 `earned` bit is pushed onto the
     * Enrollment through `Enrollment\Public`, exactly as the finalize endpoint does — never by
     * writing Enrollment's column directly.
     */
    private function grade(GradeSheet $sheet, ?int $enrollmentId, float $mark): void
    {
        if ($enrollmentId === null) {
            return;
        }

        $grade = Grade::firstOrCreate(
            ['grade_sheet_id' => $sheet->getKey(), 'enrollment_id' => $enrollmentId],
            Grade::derivedFrom($mark),
        );

        if ($sheet->status === GradeSheetStatus::Finalized) {
            $earned = (float) $grade->gpa_points >= (float) config('grading.min_grade_per_course');
            app(EnrollmentRecords::class)->recordOutcome($enrollmentId, $earned);
        }
    }

    /**
     * The id of one Student's Enrollment in one Offering — `null` when the registration helper
     * short-circuited (it is a no-op once an Approved Initial request already exists for the
     * Student's Semester), which keeps a partially-seeded database from crashing the run.
     */
    private function enrollmentId(StudentSummary $student, CourseOffering $offering): ?int
    {
        $id = Enrollment::query()
            ->where('student_id', $student->studentId)
            ->where('course_offering_id', $offering->getKey())
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
