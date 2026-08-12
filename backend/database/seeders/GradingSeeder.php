<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsAcademicWorld;
use Database\Seeders\Concerns\SeedsGradedHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\CorrectionLine;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;

/**
 * Seeds the Phase-4 Grading world (spec §Seeder, ticket 10) on top of the Phase-3 one, so the
 * whole Grade → sign → finalize → GPA → Incomplete → Fail-Withdrawal lifecycle is demonstrable
 * the moment the app boots, and the multi-role headline smoke has something real to drive.
 *
 * **The hard part is time.** Nothing here works on a term still running: grades need a
 * *finished* Semester, and an Incomplete only becomes overdue once the **following** Semester
 * is itself past its week 10 (its `starts_at` + 63 days, ADR-0018). So this extends Phase 3's
 * two out-of-phase Semesters backwards with three completed ones, windows computed **relative
 * to seed time** exactly as Phase 3 does, giving the chronology
 * `Fall 2024 → Spring 2025 → Fall 2025 → Spring 2026 (running) → Fall 2026 (registering)`:
 *
 * - **Fall 2024** — long finished and **past its grade-submission deadline**, carrying a Sheet
 *   still only Submitted, so `grading:flag-late-sheets` has a subject on seed day.
 * - **Spring 2025** — finished, but its grade-submission deadline is deliberately **still
 *   ahead**: this is the demo term, where grades are genuinely enterable and nothing in it is
 *   late. Because Fall 2025 has long passed its week 10, an Incomplete declared on a Spring
 *   2025 Enrollment — including the one the headline smoke declares — is **immediately
 *   overdue**, so `grading:convert-expired-incompletes` converts it the same day.
 * - **Fall 2025** — finished, and its follower (Phase 3's running Spring 2026) has *not* yet
 *   reached week 10, so an Incomplete here is outstanding but **not** overdue: the other half
 *   of the conversion command's behaviour.
 *
 * Around that calendar it seeds Grade Sheets resting in **every** state (Draft, Submitted
 * awaiting review, Returned with a note, Finalized, plus a finalized Correction round), a
 * Department Head per Department **and one university-wide** — with one Department deliberately
 * left uncovered so the admin screen's warning is real — and a cohort of Students each resting
 * in one interesting state: a superseded Retake, a must-retake prep grade, an overdue
 * Incomplete, an outstanding-but-not-overdue Incomplete, a corrected grade, and a failed
 * external elective. The **null-CGPA** case needs no new Student: Phase 3's `student.smoke`
 * has Enrollments and no finalized Grade at all, which is exactly it.
 *
 * Students are minted through the same `Identity\Public::admit()` seam the live accept uses
 * (ADR-0013), under the reserved `student.*` seed-email namespace Phase 3 established, so they
 * never clash with anything a smoke creates. Runs after the Catalog, Admissions and Enrollment
 * seeders it reads; idempotent (keyed by natural keys / emails), so re-running `db:seed`
 * changes nothing.
 *
 * Tests never rely on this seeder except `Tests\Feature\Grading\GradingSeederTest`, which
 * asserts the shape promised above so a drifting seed fails loudly rather than silently
 * weakening the smoke.
 */
class GradingSeeder extends Seeder
{
    use SeedsAcademicWorld;
    use SeedsGradedHistory;

    public function run(): void
    {
        // The Phase-2 accepted Student anchors the cohort's admission cycle, exactly as the
        // Phase-3 seeder does; without it the Admissions seeder has not run yet.
        $adamUser = User::query()->where('email', 'applicant.accepted@seed.ejust.test')->first();
        $adam = $adamUser !== null ? app(StudentDirectory::class)->find((int) $adamUser->getKey()) : null;
        if ($adam === null) {
            return;
        }

        $program = Program::query()->where('code', 'MSC-CE')->firstOrFail();
        $advisor = User::query()->where('email', 'advisor.amina@ejust.test')->firstOrFail();

        // The two seeded R04 instructors Phase 3 already uses. r04 is the canonical Instructor
        // the headline smoke signs in as, and teaches only Courses it does *not* teach in the
        // Phase-3 world, so no "my teaching"/"grade sheets" row is ever ambiguous.
        $r04 = User::query()->where('email', 'r04@ejust.test')->firstOrFail();
        $hoda = User::query()->where('email', 'interviewer.hoda@ejust.test')->firstOrFail();

        $courses = Course::query()
            ->whereIn('code', ['DEP501', 'DEP502', 'CSE511', 'CSE512', 'CSE531', 'CSE571', 'CSE631'])
            ->get()
            ->keyBy('code');

        // ── Department Heads: who reviews what (ticket 02) ───────────────────────────────
        $heads = $this->departmentHeads();

        // ── The three completed Semesters (evergreen: windows relative to now) ───────────
        $fall2024 = $this->fall2024();
        $spring2025 = $this->spring2025();
        $fall2025 = $this->fall2025();

        // ── Course Offerings over the Phase-1 anchor Programs ────────────────────────────
        $f24Cse511 = $this->offering($fall2024, $courses['CSE511'], $hoda, 30, [[Weekday::Monday, '09:00', '11:00']]);
        $f24Dep502 = $this->offering($fall2024, $courses['DEP502'], $r04, 40, [[Weekday::Sunday, '09:00', '11:00']]);

        $s25Dep501 = $this->offering($spring2025, $courses['DEP501'], $hoda, 40, [[Weekday::Sunday, '09:00', '11:00']]);
        $s25Cse511 = $this->offering($spring2025, $courses['CSE511'], $hoda, 30, [[Weekday::Monday, '09:00', '11:00']]);
        $s25Cse512 = $this->offering($spring2025, $courses['CSE512'], $hoda, 30, [[Weekday::Tuesday, '09:00', '11:00']]);
        $s25Cse571 = $this->offering($spring2025, $courses['CSE571'], $hoda, 20, [[Weekday::Thursday, '09:00', '11:00']]);
        $s25Cse631 = $this->offering($spring2025, $courses['CSE631'], $hoda, 20, [[Weekday::Thursday, '12:00', '14:00']]);
        // The headline smoke's Offering: r04 teaches it, its Sheet is still Draft, and it is the
        // only CSE531 r04 teaches in any Semester.
        $s25Cse531 = $this->offering($spring2025, $courses['CSE531'], $r04, 30, [[Weekday::Wednesday, '09:00', '11:00']]);

        $f25Cse512 = $this->offering($fall2025, $courses['CSE512'], $hoda, 30, [[Weekday::Tuesday, '09:00', '11:00']]);
        $f25Cse531 = $this->offering($fall2025, $courses['CSE531'], $hoda, 30, [[Weekday::Wednesday, '09:00', '11:00']]);

        // ── The cohort ───────────────────────────────────────────────────────────────────
        $C = EnrollmentClassification::class;
        $M = RegistrationMode::class;
        $S = CourseStatus::class;

        $cohort = [
            // key         => [email local part, name (en), name (ar), gender, enrollment term, national id]
            'nour' => ['student.graded', 'Nour Adel', 'نور عادل', 'Female', 'Spring 2025', '32000000000001'],
            'karim' => ['student.deferred', 'Karim Fouad', 'كريم فؤاد', 'Male', 'Spring 2025', '32000000000002'],
            'rania' => ['student.retaken', 'Rania Selim', 'رانيا سليم', 'Female', 'Fall 2024', '32000000000003'],
            'hassan' => ['student.mustretake', 'Hassan Zaki', 'حسن زكي', 'Male', 'Spring 2025', '32000000000004'],
            'dalia' => ['student.incomplete', 'Dalia Wagih', 'داليا وجيه', 'Female', 'Fall 2025', '32000000000005'],
            'omar' => ['student.overdue', 'Omar Bakr', 'عمر بكر', 'Male', 'Spring 2025', '32000000000006'],
            'farida' => ['student.corrected', 'Farida Nagi', 'فريدة ناجي', 'Female', 'Fall 2024', '32000000000007'],
            'ziad' => ['student.external', 'Ziad Helmy', 'زياد حلمي', 'Male', 'Spring 2025', '32000000000008'],
        ];

        /** @var array<string, StudentSummary> $students */
        $students = [];
        foreach ($cohort as $key => [$local, $nameEn, $nameAr, $gender, $term, $nationalId]) {
            $students[$key] = $this->student(
                "{$local}@seed.ejust.test", $nameEn, $nameAr, $gender,
                $advisor, $program, $term, $adam->admissionCycleId, $nationalId,
            );
        }

        $advisorId = (int) $advisor->getKey();

        // ── Fall 2024 registrations ──────────────────────────────────────────────────────
        $this->enrollViaApprovedRequest($students['rania']->studentId, $advisorId, $fall2024, [
            ['offering' => $f24Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $f24Dep502, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
        ]);
        $this->enrollViaApprovedRequest($students['farida']->studentId, $advisorId, $fall2024, [
            ['offering' => $f24Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $f24Dep502, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
        ]);
        $this->enrollViaApprovedRequest($students['karim']->studentId, $advisorId, $fall2024, [
            ['offering' => $f24Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // ── Spring 2025 registrations ────────────────────────────────────────────────────
        $this->enrollViaApprovedRequest($students['nour']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Dep501, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
            ['offering' => $s25Cse531, 'mode' => $M::Regular, 'class' => $C::InternalElective, 'status' => $S::Regular, 'ch' => 3],
        ]);
        $this->enrollViaApprovedRequest($students['karim']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Cse531, 'mode' => $M::Regular, 'class' => $C::InternalElective, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $s25Cse571, 'mode' => $M::Regular, 'class' => $C::ResearchCourse, 'status' => $S::Regular, 'ch' => 3],
        ]);
        // Rania's second CSE511 is a Retake — her Fall 2024 D earned nothing (FR-REG-006.1) —
        // and it supersedes the earlier Attempt because its Semester starts later.
        $this->enrollViaApprovedRequest($students['rania']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Retake, 'ch' => 3],
        ]);
        $this->enrollViaApprovedRequest($students['hassan']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Dep501, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
            ['offering' => $s25Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);
        $this->enrollViaApprovedRequest($students['omar']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $s25Cse512, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);
        $this->enrollViaApprovedRequest($students['ziad']->studentId, $advisorId, $spring2025, [
            ['offering' => $s25Cse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $s25Cse571, 'mode' => $M::Regular, 'class' => $C::ResearchCourse, 'status' => $S::Regular, 'ch' => 3],
            // The external elective FR-REG-006.4 is about: a PhD-curriculum Course taken by an
            // MSc Student, failed below, so the lifetime external allowance is freed again.
            ['offering' => $s25Cse631, 'mode' => $M::Regular, 'class' => $C::ExternalElective, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // ── Fall 2025 registrations ──────────────────────────────────────────────────────
        $this->enrollViaApprovedRequest($students['dalia']->studentId, $advisorId, $fall2025, [
            ['offering' => $f25Cse512, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $f25Cse531, 'mode' => $M::Regular, 'class' => $C::InternalElective, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // ── Fall 2024 Sheets ─────────────────────────────────────────────────────────────
        // Finalized: the history the Spring 2025 Retake and the Correction round both act on.
        $f24Cse511Sheet = $this->finalizedSheet($f24Cse511, GradeSheetKind::Initial, $hoda, $heads['ce'], 47);
        $this->grade($f24Cse511Sheet, $this->enrollmentId($students['rania'], $f24Cse511), 62.00); // D  — nothing earned
        $this->grade($f24Cse511Sheet, $this->enrollmentId($students['karim'], $f24Cse511), 81.00); // B
        $this->grade($f24Cse511Sheet, $this->enrollmentId($students['farida'], $f24Cse511), 71.00); // C  — corrected below

        // The Correction round (ADR-0023): only the affected line, finalized *after* the Initial
        // one, so it becomes the current Grade while the C stays visible as history.
        $f24Cse511Correction = $this->finalizedSheet($f24Cse511, GradeSheetKind::Correction, $hoda, $heads['ce'], 40);
        $faridaCse511 = $this->enrollmentId($students['farida'], $f24Cse511);
        CorrectionLine::firstOrCreate(['grade_sheet_id' => $f24Cse511Correction->getKey(), 'enrollment_id' => $faridaCse511]);
        $this->grade($f24Cse511Correction, $faridaCse511, 86.00); // B+ — supersedes the C

        // Submitted and never finalized, in a Semester whose grade-submission deadline has
        // passed: the `grading:flag-late-sheets` subject, and a second item in the CE Head's queue.
        $f24Dep502Sheet = $this->sheet($f24Dep502, GradeSheetKind::Initial, GradeSheetStatus::Submitted, [
            'signed_by' => $r04->getKey(), 'signed_at' => Carbon::now()->subWeeks(44),
        ]);
        $this->grade($f24Dep502Sheet, $this->enrollmentId($students['rania'], $f24Dep502), 84.00);
        $this->grade($f24Dep502Sheet, $this->enrollmentId($students['farida'], $f24Dep502), 76.00);

        // ── Spring 2025 Sheets ───────────────────────────────────────────────────────────
        $s25Dep501Sheet = $this->finalizedSheet($s25Dep501, GradeSheetKind::Initial, $hoda, $heads['ce'], 25);
        $this->grade($s25Dep501Sheet, $this->enrollmentId($students['nour'], $s25Dep501), 92.00);  // A
        $this->grade($s25Dep501Sheet, $this->enrollmentId($students['hassan'], $s25Dep501), 66.00); // D+ — Prep below C+ ⇒ must retake

        $s25Cse511Sheet = $this->finalizedSheet($s25Cse511, GradeSheetKind::Initial, $hoda, $heads['ce'], 25);
        $this->grade($s25Cse511Sheet, $this->enrollmentId($students['rania'], $s25Cse511), 84.00); // B  — supersedes the D
        $this->grade($s25Cse511Sheet, $this->enrollmentId($students['hassan'], $s25Cse511), 77.00); // C+
        $this->grade($s25Cse511Sheet, $this->enrollmentId($students['omar'], $s25Cse511), 90.00); // A
        $this->grade($s25Cse511Sheet, $this->enrollmentId($students['ziad'], $s25Cse511), 74.00); // C  — counts, earns nothing

        // Omar's Incomplete: declared in Spring 2025, whose follower (Fall 2025) is long past its
        // week 10 — so it is genuinely **overdue** and the conversion command has a subject.
        $s25Cse512Sheet = $this->finalizedSheet($s25Cse512, GradeSheetKind::Initial, $hoda, $heads['ce'], 25);
        $this->incomplete(
            $s25Cse512Sheet, $this->enrollmentId($students['omar'], $s25Cse512), $hoda,
            85, 'Hospitalised the week the final project was due; 85% of the coursework was submitted.',
        );

        // Returned with a note — editable and re-signable again (ADR-0023).
        $s25Cse571Sheet = $this->sheet($s25Cse571, GradeSheetKind::Initial, GradeSheetStatus::Returned, [
            'signed_by' => $hoda->getKey(), 'signed_at' => Carbon::now()->subWeeks(22),
            'reviewed_by' => $heads['ce']->getKey(), 'reviewed_at' => Carbon::now()->subWeeks(21),
            'review_note' => 'Two project marks look transposed against the submitted reports — please recheck before I finalize.',
        ]);
        $this->grade($s25Cse571Sheet, $this->enrollmentId($students['karim'], $s25Cse571), 85.00);
        $this->grade($s25Cse571Sheet, $this->enrollmentId($students['ziad'], $s25Cse571), 80.00);

        $s25Cse631Sheet = $this->finalizedSheet($s25Cse631, GradeSheetKind::Initial, $hoda, $heads['ce'], 25);
        $this->grade($s25Cse631Sheet, $this->enrollmentId($students['ziad'], $s25Cse631), 48.00); // F — frees the external allowance

        // The headline smoke's Sheet: Draft and blank, exactly as a lazy first open leaves it,
        // so the Instructor enters both marks and the Incomplete themselves.
        $this->sheet($s25Cse531, GradeSheetKind::Initial, GradeSheetStatus::Draft);

        // ── Fall 2025 Sheets ─────────────────────────────────────────────────────────────
        // Dalia's Incomplete: its follower (the running Spring 2026) has *not* reached week 10,
        // so this one is outstanding and **not** overdue — the conversion command must skip it.
        $f25Cse512Sheet = $this->finalizedSheet($f25Cse512, GradeSheetKind::Initial, $hoda, $heads['ce'], 7);
        $this->incomplete(
            $f25Cse512Sheet, $this->enrollmentId($students['dalia'], $f25Cse512), $hoda,
            78, 'Laboratory equipment failure delayed the final experiment; 78% of the coursework is complete.',
        );

        $f25Cse531Sheet = $this->finalizedSheet($f25Cse531, GradeSheetKind::Initial, $hoda, $heads['ce'], 7);
        $this->grade($f25Cse531Sheet, $this->enrollmentId($students['dalia'], $f25Cse531), 88.00); // B+
    }

    /** Fall 2024 — the past-its-deadline term (see the class docblock). */
    private function fall2024(): Semester
    {
        return $this->semester(Term::Fall, 2024, [
            'registration_opens_at' => '-68 weeks',
            'registration_closes_at' => '-66 weeks',
            'starts_at' => '-62 weeks',
            'add_drop_ends_at' => '-60 weeks',
            'late_add_ends_at' => '-59 weeks',
            'withdrawal_deadline_at' => '-54 weeks',
            'ends_at' => '-48 weeks',
            'grade_submission_deadline_at' => '-46 weeks',
        ]);
    }

    /** Spring 2025 — the demo term: finished, on time, and its Incompletes already overdue. */
    private function spring2025(): Semester
    {
        return $this->semester(Term::Spring, 2025, [
            'registration_opens_at' => '-46 weeks',
            'registration_closes_at' => '-44 weeks',
            'starts_at' => '-40 weeks',
            'add_drop_ends_at' => '-38 weeks',
            'late_add_ends_at' => '-37 weeks',
            'withdrawal_deadline_at' => '-32 weeks',
            'ends_at' => '-26 weeks',
            'grade_submission_deadline_at' => '+3 weeks',
        ]);
    }

    /** Fall 2025 — what makes a Spring 2025 Incomplete overdue, while its own are not yet. */
    private function fall2025(): Semester
    {
        return $this->semester(Term::Fall, 2025, [
            'registration_opens_at' => '-26 weeks',
            'registration_closes_at' => '-24 weeks',
            'starts_at' => '-22 weeks',
            'add_drop_ends_at' => '-20 weeks',
            'late_add_ends_at' => '-19 weeks',
            'withdrawal_deadline_at' => '-14 weeks',
            'ends_at' => '-8 weeks',
            'grade_submission_deadline_at' => '+6 weeks',
        ]);
    }

    /**
     * The Department-Head routing map (ticket 02): a Head per Department — the canonical R06
     * covers Computer Engineering, so every Sheet above routes to them — plus one assigned
     * **university-wide** (a NULL Department), which is the scope the department-less
     * Preparatory Courses PRE401–404 fall under (ADR-0010/0023). **Mathematics is deliberately
     * left uncovered**, so the admin screen's "these Departments have no Head" warning has
     * something real to report and a demo can see grades that would strand in review.
     *
     * @return array<string, User>
     */
    private function departmentHeads(): array
    {
        $heads = [
            'ce' => User::query()->where('email', 'r06@ejust.test')->firstOrFail(),
            'ee' => $this->headUser('head.electrical@seed.ejust.test', 'Prof. Nabil Fahmy'),
            'it' => $this->headUser('head.it@seed.ejust.test', 'Prof. Yara Sobhy'),
            'university' => $this->headUser('head.university@seed.ejust.test', 'Prof. Salma Ghoneim'),
        ];

        foreach (['ce' => 'CE', 'ee' => 'EE', 'it' => 'IT'] as $key => $departmentCode) {
            $department = Department::query()->where('code', $departmentCode)->first();
            if ($department !== null) {
                $this->assignHead($heads[$key], (int) $department->getKey());
            }
        }
        $this->assignHead($heads['university'], null);

        return $heads;
    }

    /** A seeded R06 account, idempotent by email and sharing the dev password. */
    private function headUser(string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::SEED_PASSWORD), 'email_verified_at' => Carbon::now()],
        );
        $user->assignRole(Role::DepartmentHead);

        return $user;
    }

    /**
     * One routing row, keyed by its own (user, department) uniqueness. A **null** department is
     * the university-wide scope, not a missing argument.
     */
    private function assignHead(User $user, ?int $departmentId): void
    {
        DepartmentHeadAssignment::firstOrCreate(['user_id' => $user->getKey(), 'department_id' => $departmentId]);
    }

    /**
     * One Incomplete declared on a round in place of a mark, with the Enrollment moved to the
     * Incomplete Course Status through `Enrollment\Public` — Enrollment stays the sole writer of
     * that column. The status write happens **only on the run that created the declaration**:
     * `markIncomplete()` snapshots the current status so a resolution can restore it, so calling
     * it twice would remember "Incomplete" as the status to go back to.
     */
    private function incomplete(GradeSheet $sheet, ?int $enrollmentId, User $declaredBy, int $completionPercentage, string $reason): void
    {
        if ($enrollmentId === null) {
            return;
        }

        $incomplete = Incomplete::firstOrCreate(
            ['declared_on_grade_sheet_id' => $sheet->getKey(), 'enrollment_id' => $enrollmentId],
            [
                'declared_by' => $declaredBy->getKey(),
                'completion_percentage' => $completionPercentage,
                'reason' => $reason,
            ],
        );

        if ($incomplete->wasRecentlyCreated) {
            app(EnrollmentRecords::class)->markIncomplete($enrollmentId);
        }
    }
}
