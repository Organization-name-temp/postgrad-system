<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\EnrollmentSummary;

/**
 * Builds the student-facing/reviewer-facing grade-record payload once (ticket 04 + ticket 08):
 * CGPA, Semester GPAs, and the per-course rows including supersession, Incomplete deadlines
 * and must-retake visibility. Both My Grades and the scoped oversight read call this builder
 * so there is one payload shape and one place the read-model assembly happens.
 */
final readonly class StudentGradesRecordBuilder
{
    public function __construct(
        private StudentGpaTally $tally,
        private CourseCatalog $courses,
        private IncompleteDeadlineResolver $deadlines,
    ) {}

    /** @return array{cgpa: ?float, semesters: list<array<string, mixed>>} */
    public function forStudent(int $studentId): array
    {
        $record = $this->tally->recordFor($studentId);
        $eligible = GpaCalculator::nonSuperseded($record->attempts);
        $eligibleIds = array_flip(array_map(fn (GpaAttempt $a): int => $a->enrollmentId, $eligible));
        $mustRetakeIds = array_flip($this->mustRetakeEnrollmentIds($record->enrollments));
        $cgpa = GpaCalculator::average($eligible);

        $visible = array_values(array_filter(
            $record->enrollments,
            fn (EnrollmentSummary $e): bool => isset($record->grades[$e->id])
                || StudentGpaTally::isUngradedVisible($e->courseStatus),
        ));

        if ($visible === []) {
            return ['cgpa' => $cgpa, 'semesters' => []];
        }

        $coursesById = $this->courses->findMany(array_values(array_unique(
            array_map(fn (EnrollmentSummary $e): int => $e->courseId, $visible),
        )));
        $history = FinalizedGradeHistoryLookup::forEnrollments(array_map(
            fn (EnrollmentSummary $e): int => $e->id,
            $visible,
        ));

        $rowsBySemester = [];
        foreach ($visible as $enrollment) {
            if (! isset($record->semesters[$enrollment->semesterId])) {
                continue;
            }

            $grade = $record->grades[$enrollment->id] ?? null;

            $rowsBySemester[$enrollment->semesterId][] = [
                'enrollment_id' => $enrollment->id,
                'course' => ($coursesById[$enrollment->courseId] ?? null)?->toArray(),
                'credit_hours' => $enrollment->creditHours,
                'course_status' => $enrollment->courseStatus,
                'grade' => $grade?->toLineArray(),
                'grade_history' => $grade === null
                    ? []
                    : array_map(
                        fn ($historyGrade): array => [
                            ...$historyGrade->toLineArray(),
                            'finalized_at' => $historyGrade->sheet->reviewed_at?->toIso8601String(),
                            'superseded' => true,
                        ],
                        array_values(array_filter(
                            $history[$enrollment->id] ?? [],
                            fn ($historyGrade): bool => $historyGrade->getKey() !== $grade->getKey(),
                        )),
                    ),
                'earned' => $enrollment->earned,
                'superseded' => $grade !== null && ! isset($eligibleIds[$enrollment->id]),
                'must_retake' => isset($mustRetakeIds[$enrollment->id]),
                'incomplete_deadline' => $enrollment->courseStatus === 'Incomplete'
                    ? $this->deadlines->forEnrollmentSemester($enrollment->semesterId)?->toIso8601String()
                    : null,
            ];
        }

        uksort($rowsBySemester, fn (int $a, int $b): int => $record->semesters[$b]->startsAt->getTimestamp()
            <=> $record->semesters[$a]->startsAt->getTimestamp());

        $semesters = [];
        foreach ($rowsBySemester as $semesterId => $rows) {
            $semesterEligible = array_values(array_filter(
                $eligible,
                fn (GpaAttempt $a): bool => $a->semesterId === $semesterId,
            ));

            $semesters[] = [
                'semester' => $this->semesterArray($record->semesters[$semesterId]),
                'gpa' => GpaCalculator::average($semesterEligible),
                'courses' => $rows,
            ];
        }

        return ['cgpa' => $cgpa, 'semesters' => $semesters];
    }

    /**
     * @param  list<EnrollmentSummary>  $enrollments
     * @return list<int>
     */
    private function mustRetakeEnrollmentIds(array $enrollments): array
    {
        $byCourse = [];
        foreach ($enrollments as $enrollment) {
            $byCourse[$enrollment->courseId][] = $enrollment;
        }

        $marked = [];
        foreach ($byCourse as $attempts) {
            $mustRetake = false;

            foreach ($attempts as $attempt) {
                if (! in_array($attempt->classification, ['Preparatory', 'Core'], true)) {
                    continue;
                }

                if ($attempt->earned === true) {
                    $mustRetake = false;

                    continue;
                }

                if ($attempt->earned === false) {
                    $mustRetake = true;
                }
            }

            // The marker hangs off the Course's latest Attempt — the last of a list built in
            // Enrollment's own id order, and never empty, since a key only exists because an
            // Attempt was pushed under it.
            if ($mustRetake) {
                $marked[] = $attempts[count($attempts) - 1]->id;
            }
        }

        return $marked;
    }

    /** @return array<string, mixed> */
    private function semesterArray(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
        ];
    }
}
