<?php

namespace Modules\Grading\Internal\Http\Concerns;

use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Enums\LetterGrade;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;

/**
 * The Sheet and line wire shapes shared by the Instructor's editor and the Department Head's
 * review screen (ticket 03) — one home for how a round and its lines render, so the two
 * screens can never quietly disagree about what a Sheet or a line looks like.
 */
trait BuildsSheetPayload
{
    /**
     * One Sheet's wire shape — the round's identity and state, which the SPA renders as a
     * StatusChip.
     *
     * @return array{id: int, course_offering_id: int, kind: string, status: string, signed_at: string|null, reviewed_at: string|null, review_note: string|null}
     */
    private function sheetArray(GradeSheet $sheet): array
    {
        return [
            'id' => (int) $sheet->getKey(),
            'course_offering_id' => (int) $sheet->course_offering_id,
            'kind' => $sheet->kind->value,
            'status' => $sheet->status->value,
            'signed_at' => $sheet->signed_at?->toIso8601String(),
            'reviewed_at' => $sheet->reviewed_at?->toIso8601String(),
            'review_note' => $sheet->review_note,
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

    /**
     * The Offering's grade distribution — a count per Letter Grade, zero-filled so the
     * Department Head's view always renders the whole scale (a view, not a report). Every
     * `LetterGrade` case appears in scale order (A+ highest first, matching the enum's own
     * declaration order).
     *
     * @return list<array{letter: string, count: int}>
     */
    private function distributionArray(GradeSheet $sheet): array
    {
        $counts = Grade::query()
            ->where('grade_sheet_id', $sheet->getKey())
            ->selectRaw('letter_grade, count(*) as aggregate')
            ->groupBy('letter_grade')
            ->pluck('aggregate', 'letter_grade');

        return array_map(fn (LetterGrade $letter): array => [
            'letter' => $letter->value,
            'count' => (int) ($counts[$letter->value] ?? 0),
        ], LetterGrade::cases());
    }
}
