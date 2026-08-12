<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsAcademicWorld;
use Database\Seeders\Concerns\SeedsGradedHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

/**
 * Gives the **canonical role accounts** a real academic footprint (phase 4.5, ticket 02), so
 * signing in as any `rNN` role account shows a populated, representative system rather than an
 * empty one.
 *
 * Those accounts were built as **authorization fixtures**: they exist to prove the §3.2
 * permission matrix, and several own no records at all — every persona with a real footprint
 * lives under the named `advisor.*` / `student.*` namespace instead. That was invisible for four
 * phases, because every screen was a list you navigate *into*. It becomes glaring the moment the
 * landing page is made of counts: an Instructor's Dashboard would render rich while an Advisor's
 * rendered zeros beside it, and the difference would read as broken code rather than an empty
 * account.
 *
 * Three accounts need it:
 *
 * - **`r02`** becomes an actual Student — minted through the same `Identity\Public::admit()`
 *   seam the live accept uses (ADR-0013), which swaps R01 → R02 and so leaves its canonical role
 *   set exactly as it was — with Enrollments across two completed Semesters and finalized
 *   Grades, so a CGPA *and* at least one Semester GPA are computable for them.
 * - **`r03`** gains advisees, one of whom is resting a registration request in their queue.
 * - **`multi`** — the Advisor + Instructor account seeded in Phase 0 precisely to prove the
 *   multi-role path — gains **both** advisees with a pending request **and** Course Offerings
 *   carrying an unfinalized Grade Sheet. It is the only account that demonstrates the
 *   composition rule the whole Dashboard rests on (ADR-0024): one page showing an advisee queue
 *   *and* an ungraded-sheets Card, with no role switcher anywhere. Left empty, the phase ships
 *   its headline architectural property with no way to show it.
 *
 * It adds to the seeded world and **disturbs none of it**: it opens no Semester, assigns no
 * Department Head, and touches no existing Student, Offering or Sheet except to add lines to
 * rosters and Grades to already-Finalized rounds. The canonical accounts that already carry data
 * — Instructor, Student Affairs, Department Head, Dean — are not written here at all.
 *
 * Runs last: it reads the Catalog, Admissions, Enrollment and Grading worlds. Idempotent (keyed
 * by natural keys / emails) and deterministic, and it opens no window of its own, so the seeded
 * world stays evergreen. New Students stay inside the reserved `student.*` seed-email namespace,
 * so the Playwright suites' own data still cannot collide with seeded records.
 */
class RoleAccountSeeder extends Seeder
{
    use SeedsAcademicWorld;
    use SeedsGradedHistory;

    public function run(): void
    {
        // The Phase-2 accepted Student anchors the admission cycle every mint below belongs to,
        // exactly as the Phase-3 and Phase-4 seeders do.
        $adamUser = User::query()->where('email', 'applicant.accepted@seed.ejust.test')->first();
        $adam = $adamUser !== null ? app(StudentDirectory::class)->find((int) $adamUser->getKey()) : null;
        if ($adam === null) {
            return; // Admissions seeder has not minted the accepted student yet.
        }

        // The Semesters are **read, never opened**: the Phase-3/4 calendar is what the whole
        // demo turns on, so a missing one means an earlier seeder bailed and there is nothing
        // here to build on.
        $semesters = Semester::query()->get()->keyBy(
            fn (Semester $semester): string => "{$semester->term->value} {$semester->year}",
        );
        foreach (['Fall 2024', 'Spring 2025', 'Fall 2025', 'Fall 2026'] as $required) {
            if (! $semesters->has($required)) {
                return;
            }
        }

        $program = Program::query()->where('code', 'MSC-CE')->firstOrFail();
        $amina = User::query()->where('email', 'advisor.amina@ejust.test')->firstOrFail();
        $hoda = User::query()->where('email', 'interviewer.hoda@ejust.test')->firstOrFail();
        $ceHead = User::query()->where('email', 'r06@ejust.test')->firstOrFail();

        $r02 = User::query()->where('email', 'r02@ejust.test')->firstOrFail();
        $r03 = User::query()->where('email', 'r03@ejust.test')->firstOrFail();
        $multi = User::query()->where('email', 'multi@ejust.test')->firstOrFail();

        $this->seedCanonicalStudent($r02, $amina, $program, $adam, $semesters, $hoda, $ceHead);

        // r03@'s advisees register for the term that is currently open, so their pending request
        // is one an Advisor could act on today.
        $this->advisees($r03, 'r03', '34', 'Fall 2026', [
            ['Mariam Shukri', 'مريم شكري', 'Female'],
            ['Bassel Gaber', 'باسل جابر', 'Male'],
        ], $program, $adam, $semesters);

        // multi@'s advisees sit a term earlier, because they are also the roster of the
        // Offerings it teaches — one account, two roles, the same people.
        $multiAdvisees = $this->advisees($multi, 'multi', '35', 'Fall 2025', [
            ['Hana Reda', 'هناء رضا', 'Female'],
            ['Tamer Fouad', 'تامر فؤاد', 'Male'],
        ], $program, $adam, $semesters);

        $this->seedInstructorOfferings($multi, $semesters['Fall 2025'], $multiAdvisees);
    }

    /**
     * `r02@` as a real Student: Grades on the already-Finalized Fall 2024 and Spring 2025 rounds
     * for the Courses it registers, which is what makes a CGPA and per-Semester GPAs computable
     * — and therefore what the My Academic Record Card has to show. Adding lines to existing
     * rounds rather than opening new ones keeps every Sheet-state assertion in the Phase-4
     * seeder test true.
     *
     * The Student identity mirrors the canonical account's own name, so a Grade Sheet roster and
     * the Dashboard never disagree about who this is.
     *
     * @param  Collection<string, Semester>  $semesters
     */
    private function seedCanonicalStudent(
        User $r02,
        User $advisor,
        Program $program,
        StudentSummary $adam,
        Collection $semesters,
        User $instructor,
        User $head,
    ): void {
        $fall2024 = $semesters['Fall 2024'];
        $spring2025 = $semesters['Spring 2025'];

        $student = $this->student(
            'r02@ejust.test', $r02->name, 'طالب', 'Male',
            $advisor, $program, 'Fall 2024', $adam->admissionCycleId, '33000000000001',
        );

        $f24Cse511 = $this->existingOffering($fall2024, 'CSE511');
        $s25Dep501 = $this->existingOffering($spring2025, 'DEP501');
        $s25Cse512 = $this->existingOffering($spring2025, 'CSE512');
        if ($f24Cse511 === null || $s25Dep501 === null || $s25Cse512 === null) {
            return;
        }

        $C = EnrollmentClassification::class;
        $M = RegistrationMode::class;
        $S = CourseStatus::class;
        $advisorId = (int) $advisor->getKey();

        $this->enrollViaApprovedRequest($student->studentId, $advisorId, $fall2024, [
            ['offering' => $f24Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);
        $this->enrollViaApprovedRequest($student->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Dep501, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
            ['offering' => $s25Cse512, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // The Initial rounds on those Offerings were Finalized by the Phase-4 seeder, and
        // `finalizedSheet` is keyed by (Offering, kind) — so this resolves them rather than
        // opening anything. Two graded Semesters: a CGPA and a latest Semester GPA.
        $this->grade(
            $this->finalizedSheet($f24Cse511, GradeSheetKind::Initial, $instructor, $head, 47),
            $this->enrollmentId($student, $f24Cse511),
            88.00, // B+
        );
        $this->grade(
            $this->finalizedSheet($s25Dep501, GradeSheetKind::Initial, $instructor, $head, 25),
            $this->enrollmentId($student, $s25Dep501),
            91.00, // A-
        );
        $this->grade(
            $this->finalizedSheet($s25Cse512, GradeSheetKind::Initial, $instructor, $head, 25),
            $this->enrollmentId($student, $s25Cse512),
            85.00, // B+
        );
    }

    /**
     * Two advisees for an Advisor account: one resting a **Pending** registration request in
     * that Advisor's queue, the other already approved — so an Advisee Requests Card shows a
     * real pending count *and* a real advisee count, and the two are different numbers.
     *
     * Both are minted under the reserved `student.*` seed-email namespace. `$slug` and
     * `$idPrefix` keep each Advisor's advisees on their own email and national-id block, so two
     * calls can never collide on either unique column.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $identities  [name (en), name (ar), gender]
     * @param  Collection<string, Semester>  $semesters
     * @return list<StudentSummary>
     */
    private function advisees(
        User $advisor,
        string $slug,
        string $idPrefix,
        string $enrollmentTerm,
        array $identities,
        Program $program,
        StudentSummary $adam,
        Collection $semesters,
    ): array {
        $students = [];
        foreach ([['waiting', 0], ['settled', 1]] as [$key, $index]) {
            [$nameEn, $nameAr, $gender] = $identities[$index];
            $students[$key] = $this->student(
                "student.{$slug}.{$key}@seed.ejust.test", $nameEn, $nameAr, $gender,
                $advisor, $program, $enrollmentTerm, $adam->admissionCycleId,
                $idPrefix.'00000000000'.($index + 1),
            );
        }

        // The request rests in the currently-open registration term whatever term the advisees
        // were admitted in — an Advisor's queue is about what is open now.
        $fall2026 = $semesters['Fall 2026'];
        $cse511 = $this->existingOffering($fall2026, 'CSE511');
        $cse512 = $this->existingOffering($fall2026, 'CSE512');

        if ($cse511 !== null && $cse512 !== null) {
            $M = RegistrationMode::class;

            $this->pendingRequest($students['waiting']->studentId, $fall2026, [
                ['offering' => $cse511, 'mode' => $M::Regular],
                ['offering' => $cse512, 'mode' => $M::Regular],
            ]);
            $this->enrollViaApprovedRequest($students['settled']->studentId, (int) $advisor->getKey(), $fall2026, [
                [
                    'offering' => $cse511, 'mode' => $M::Regular,
                    'class' => EnrollmentClassification::Core, 'status' => CourseStatus::Regular, 'ch' => 3,
                ],
            ]);
        }

        return array_values($students);
    }

    /**
     * The Instructor half of `multi@`: two Fall 2025 Offerings of its own, one carrying a
     * **Draft** Grade Sheet over a real roster and one never opened at all — so its "still to
     * grade" count is genuine on both counts.
     *
     * Fall 2025 is deliberate: it has finished, so grades are enterable, but its
     * grade-submission deadline is still ahead, so nothing here reads as late and the Phase-4
     * late-flagging demo keeps its single intended subject. **Draft**, not Submitted, so the
     * Department Head's review queue is likewise untouched.
     *
     * @param  list<StudentSummary>  $roster
     */
    private function seedInstructorOfferings(User $instructor, Semester $semester, array $roster): void
    {
        $courses = Course::query()->whereIn('code', ['CSE511', 'DEP501'])->get()->keyBy('code');
        if ($courses->count() < 2) {
            return;
        }

        // Neither Course is offered in Fall 2025 by an earlier seeder, so these are new
        // Offerings rather than a second Offering of an existing one.
        $cse511 = $this->offering($semester, $courses['CSE511'], $instructor, 30, [[Weekday::Monday, '12:00', '14:00']]);
        $dep501 = $this->offering($semester, $courses['DEP501'], $instructor, 40, [[Weekday::Sunday, '12:00', '14:00']]);

        foreach ($roster as $student) {
            $this->enrollViaApprovedRequest($student->studentId, (int) $instructor->getKey(), $semester, [
                [
                    'offering' => $cse511, 'mode' => RegistrationMode::Regular,
                    'class' => EnrollmentClassification::Core, 'status' => CourseStatus::Regular, 'ch' => 3,
                ],
                [
                    'offering' => $dep501, 'mode' => RegistrationMode::Regular,
                    'class' => EnrollmentClassification::Preparatory, 'status' => CourseStatus::Regular, 'ch' => 1,
                ],
            ]);
        }

        // One Sheet opened and left Draft; DEP501 keeps no Sheet at all, because a lazy first
        // open is what creates one (ADR-0023) and an Offering nobody has opened yet is the other
        // half of what an Instructor still has to do.
        $this->sheet($cse511, GradeSheetKind::Initial, GradeSheetStatus::Draft);
    }

    /** One already-seeded Offering by Semester and Course code, or `null` if it is absent. */
    private function existingOffering(Semester $semester, string $courseCode): ?CourseOffering
    {
        $courseId = Course::query()->where('code', $courseCode)->value('id');
        if ($courseId === null) {
            return null;
        }

        return CourseOffering::query()
            ->where('semester_id', $semester->getKey())
            ->where('course_id', $courseId)
            ->first();
    }
}
