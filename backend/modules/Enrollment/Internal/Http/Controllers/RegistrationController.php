<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Http\Resources\EligibleCourseResource;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Support\EligibilityClassifier;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;

/**
 * @group Enrollment
 *
 * Student course registration (ADR-0017) — the self-serve surface a Student (R02) uses
 * under `courses.register_self`. Ticket 04 delivers the read-only eligibility list; the
 * cart and submission grow here in ticket 05.
 *
 * The eligible list is the Student's own Curriculum ∩ the currently-open Semester's
 * Offerings, plus other active graduate Programs' curriculum courses offered this
 * semester as External Electives — each line placed into its Enrollment Classification
 * by the pure {@see EligibilityClassifier}. Every cross-module fact is read through a
 * Public facade (no FK, ADR-0012): the acting Student through Identity\Public
 * {@see StudentDirectory}, their degree through {@see ProgramCatalog}, their Curriculum
 * through {@see CurriculumCatalog}, the open Semester through {@see AcademicCalendar},
 * and each Offering's Course/Instructor through {@see CourseCatalog}/{@see Directory}.
 */
class RegistrationController
{
    use ResolvesOfferingRefs;

    /**
     * List the acting Student's eligible courses for the open Semester.
     *
     * The courses the Student may register for right now, grouped client-side by their
     * Enrollment Classification (Preparatory / Core / Internal Elective / External
     * Elective / Research Course). Each line carries the Offering's instructor, meeting
     * times, seats remaining and the course's total CH. Courses in the Curriculum with
     * no Offering this semester are absent (ADR-0017); a course that classifies for no
     * class is omitted. When no registration window is open, `semester` is null and the
     * list is empty. Gated on `courses.register_self` (the acting Student, R02).
     *
     * @response 200 {"data": {"semester": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-15T00:00:00+00:00", "is_registration_open": true}, "courses": [{"offering_id": 1, "classification": "Core", "capacity": 30, "seats_remaining": 30, "total_ch": 3, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}}
     * @response 403 scenario="no student record" {"message": "You do not have a student record to register with."}
     */
    public function eligibleCourses(
        Request $request,
        StudentDirectory $students,
        AcademicCalendar $calendar,
        ProgramCatalog $programs,
        CurriculumCatalog $curriculum,
        CourseCatalog $courses,
        Directory $directory,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $student = $students->find($userId);
        abort_if($student === null, 403, 'You do not have a student record to register with.');

        $semester = $calendar->openForRegistration();
        $program = $programs->find($student->programId);

        // No open window, or a dangling Program reference — nothing to classify.
        if ($semester === null || $program === null) {
            return $this->payload($semester, collect());
        }

        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->with('meetings')
            ->orderBy('id')
            ->get();

        $this->attachOfferingRefs($offerings, $courses, $directory);

        $ownEntries = $curriculum->entriesForProgram($student->programId);
        $courseIds = $offerings->pluck('course_id')->filter()->unique()->values()->all();
        $programsByCourse = $curriculum->programsContainingCourses($courseIds);

        $classifier = new EligibilityClassifier;

        $eligible = $offerings->filter(function (CourseOffering $offering) use (
            $classifier,
            $program,
            $student,
            $ownEntries,
            $programsByCourse,
        ): bool {
            $course = $offering->getAttribute('resolved_course');
            if ($course === null) {
                return false; // a dropped Course reference (no FK) — never eligible
            }

            $ownCategory = ($ownEntries[$offering->course_id] ?? null)?->category;

            // "in another active graduate program's curriculum" — exclude the Student's own.
            $inOther = collect($programsByCourse[$offering->course_id] ?? [])
                ->contains(fn (int $programId): bool => $programId !== $student->programId);

            $classification = $classifier->classify(
                degree: $program->degreeLevel,
                courseLevel: $course->level,
                researchType: $course->researchType,
                ownCategory: $ownCategory,
                inOtherActiveGraduateCurriculum: $inOther,
            );

            $offering->setAttribute('classification', $classification);

            return $classification !== null;
        })->values();

        return $this->payload($semester, $eligible);
    }

    /**
     * The `{ data: { semester, courses } }` envelope. `semester` carries the open
     * window's key dates (user story 13); `courses` is the classified eligible list.
     *
     * @param  Collection<int, CourseOffering>  $eligible
     */
    private function payload(?SemesterSummary $semester, Collection $eligible): JsonResponse
    {
        return response()->json([
            'data' => [
                'semester' => $semester === null ? null : $this->semesterPayload($semester),
                'courses' => EligibleCourseResource::collection($eligible)->resolve(),
            ],
        ]);
    }

    /**
     * The open Semester's display facts and key dates, mirroring Calendar's own
     * SemesterResource shape (built here from the {@see SemesterSummary} facade DTO,
     * since Calendar's resource is Internal to that module — ADR-0012).
     *
     * @return array<string, mixed>
     */
    private function semesterPayload(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
            'starts_at' => $semester->startsAt->toIso8601String(),
            'ends_at' => $semester->endsAt->toIso8601String(),
            'registration_opens_at' => $semester->registrationOpensAt->toIso8601String(),
            'registration_closes_at' => $semester->registrationClosesAt->toIso8601String(),
            'add_drop_ends_at' => $semester->addDropEndsAt->toIso8601String(),
            'late_add_ends_at' => $semester->lateAddEndsAt->toIso8601String(),
            'withdrawal_deadline_at' => $semester->withdrawalDeadlineAt->toIso8601String(),
            'is_registration_open' => $semester->isRegistrationOpen(now()),
        ];
    }
}
