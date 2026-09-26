<?php

namespace Modules\Records\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\StudentSummary;

final readonly class TranscriptComposer
{
    public function __construct(
        private ProgramCatalog $programs,
        private CourseCatalog $courses,
        private AcademicCalendar $calendar,
        private EnrollmentRecords $enrollments,
        private GradingRecords $grading,
        private CreditHourSummaries $creditHours,
        private ExemptionDecisions $exemptions,
        private TransferDecisions $transfers,
        private WaiverDecisions $waivers,
    ) {}

    /** @return array<string, mixed> */
    public function compose(StudentSummary $student): array
    {
        $program = $this->programs->find($student->programId);
        $enrollments = $this->enrollments->enrollmentsForStudent($student->studentId);
        $grades = $this->grading->gradesFor(array_map(fn ($e): int => $e->id, $enrollments));
        $effective = array_flip(array_map(fn ($a): int => $a->enrollmentId, $this->grading->effectiveAttemptsForStudent($student->studentId)));
        $courses = $this->courses->findMany(array_values(array_unique(array_map(fn ($e): int => $e->courseId, $enrollments))));
        $semesters = [];
        foreach ($enrollments as $enrollment) {
            $semester = $this->calendar->find($enrollment->semesterId);
            abort_if($semester === null, 409, __('records.content_unavailable'));
            if (! isset($semesters[$semester->id])) {
                $semesters[$semester->id] = [
                    'semester' => ['id' => $semester->id, 'name_en' => $semester->nameEn, 'name_ar' => $semester->nameAr,
                        'starts_at' => $semester->startsAt->toDateString()],
                    'gpa' => $this->grading->semesterGpaFor($student->studentId, $semester->id), 'courses' => [],
                ];
            }
            $grade = $grades[$enrollment->id] ?? null;
            $course = $courses[$enrollment->courseId] ?? null;
            abort_if($course === null, 409, __('records.content_unavailable'));
            $semesters[$semester->id]['courses'][] = [
                'enrollment_id' => $enrollment->id,
                'course' => ['code' => $course->code, 'name_en' => $course->nameEn, 'name_ar' => $course->nameAr],
                'credit_hours' => $enrollment->creditHours, 'classification' => $enrollment->classification,
                'course_status' => $enrollment->courseStatus,
                'result' => $grade->letterGrade ?? match ($enrollment->courseStatus) {
                    'Audit' => 'AU', 'Withdrawn' => 'W', 'Incomplete' => 'I', 'FailWithdrawal' => 'FW', default => 'InProgress',
                },
                'contributes_to_cgpa' => isset($effective[$enrollment->id]),
            ];
        }
        usort($semesters, fn (array $a, array $b): int => [$a['semester']['starts_at'], $a['semester']['id']] <=> [$b['semester']['starts_at'], $b['semester']['id']]);

        return [
            'student_id' => $student->studentId, 'identity' => $student->identity?->toArray(),
            'university_id' => $student->universityId, 'enrollment_term' => $student->enrollmentTerm,
            'lifecycle_status' => $student->lifecycleStatus->value,
            'program' => $program === null ? null : ['code' => $program->code, 'name_en' => $program->nameEn,
                'name_ar' => $program->nameAr, 'degree_level' => $program->degreeLevel->value,
                'department_name_en' => $program->departmentNameEn, 'department_name_ar' => $program->departmentNameAr,
                'faculty_name_en' => $program->facultyNameEn, 'faculty_name_ar' => $program->facultyNameAr],
            'semesters' => $semesters, 'cgpa' => $this->grading->cgpaFor($student->studentId),
            'credit_decisions' => [...array_map(fn ($d): array => [
                'type' => 'Exemption', 'target_course_code' => $d->targetCourseCode,
                'credit_hours' => $d->creditHours, 'approved_at' => $d->approvedAt->toIso8601String(),
                'reason_codes' => $d->reasonCodes,
            ], $this->exemptions->effectiveForStudent($student->studentId)),
                ...array_map(fn ($d): array => [
                    'type' => 'Transfer', 'target_course_code' => $d->targetCourseCode,
                    'credit_hours' => $d->targetCreditHours, 'approved_at' => $d->approvedAt->toIso8601String(),
                    'source_institution' => $d->externalInstitution, 'source_course_code' => $d->externalCourseCode,
                    'source_course_name' => $d->externalCourseName, 'original_grade' => $d->externalGrade,
                    'normalized_grade' => $d->normalizedGrade,
                ], $this->transfers->effectiveForStudent($student->studentId)),
                ...array_map(fn ($d): array => [
                    'type' => 'Waiver', 'target_course_code' => $d->targetCourseCode,
                    'credit_hours' => $d->creditHours, 'approved_at' => $d->approvedAt,
                    'prior_course_code' => $d->priorCourseCode, 'prior_grade' => $d->priorLetterGrade,
                ], $this->waivers->effectiveForStudent($student->studentId)),
            ],
            'credit_hours' => $this->creditHours->forStudent($student->studentId)?->toArray(),
        ];
    }
}
