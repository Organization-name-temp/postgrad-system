<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\SessionSchedule;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentIdentity;
use Modules\Identity\Public\StudentSummary;

/**
 * Seeds a living, deterministic Enrollment world (spec §Seeder, ticket 11) so every
 * Phase-3 screen demonstrates the moment the app boots — the Phase-1/2 seeder precedent.
 * It stands up **two out-of-phase Semesters** with windows computed **relative to seed
 * time** (the natural keys stay Fall/Spring 2026 so the demo is evergreen):
 *
 * - **Spring 2026** — running now, inside its withdrawal window: Course Offerings over
 *   the Phase-1 anchor programs (MSc/PhD Computer Engineering + shared prep), taught by
 *   seeded R04 instructors, with approved Enrollments, generated Class Sessions and
 *   attendance history — **including an Audit Enrollment sitting below 75%** so the
 *   audit-at-risk chip is visible on seed day.
 * - **Fall 2026** — its registration window open now: Offerings ready to register into,
 *   a Student with a **Pending** request resting in an Advisor's queue, a Student
 *   positioned so a Fall registration of a Spring course derives a **Retake**, and a
 *   Student carrying a **W** on a Spring course.
 *
 * Students are minted through the same `Identity\Public::admit()` seam the live accept
 * uses (ADR-0013) — never raw rows — reusing the Phase-2 accepted Student and adding a
 * cohort under a reserved `student.*` seed-email namespace so they never clash with the
 * headline smoke's fresh data. Runs after the Catalog and Admissions seeders it reads;
 * idempotent (keyed by natural keys / emails), so re-running `db:seed` is safe.
 *
 * Tests never rely on this seeder — feature/unit tests use factories + `travelTo`; the
 * seeder exists for the demo and the multi-role Playwright smoke (spec §Testing).
 */
class EnrollmentSeeder extends Seeder
{
    /** The shared dev password every seeded account uses (as the role users do). */
    private const PASSWORD = 'password';

    public function run(): void
    {
        // The reused Phase-2 accepted Student anchors the whole world; the Admissions
        // seeder mints them through Identity\Public and must have run first.
        $adamUser = User::query()->where('email', 'applicant.accepted@seed.ejust.test')->first();
        $adam = $adamUser !== null ? app(StudentDirectory::class)->find((int) $adamUser->getKey()) : null;
        if ($adam === null) {
            return; // Admissions seeder has not minted the accepted student yet — nothing to build on.
        }

        $program = Program::query()->where('code', 'MSC-CE')->firstOrFail();
        $advisor = User::query()->where('email', 'advisor.amina@ejust.test')->firstOrFail();

        // The two seeded R04 instructors who teach the offerings (spec §Seeder). r04 is the
        // canonical Instructor the smoke signs in as; Dr. Hoda Mansour teaches the rest.
        $r04 = User::query()->where('email', 'r04@ejust.test')->firstOrFail();
        $hoda = User::query()->where('email', 'interviewer.hoda@ejust.test')->firstOrFail();

        $courses = Course::query()
            ->whereIn('code', ['DEP501', 'CSE511', 'CSE512', 'CSE531', 'CSE571', 'CSE611'])
            ->get()
            ->keyBy('code');

        // ── The two out-of-phase Semesters (evergreen: windows relative to now) ──────────
        $spring = $this->springSemester();
        $fall = $this->fallSemester();

        // ── Course Offerings — "what runs this semester" (ADR-0017) ──────────────────────
        // Spring: the running term with rosters + attendance history.
        $springCse511 = $this->offering($spring, $courses['CSE511'], $hoda, 30, [[Weekday::Monday, '09:00', '11:00']]);
        $springCse531 = $this->offering($spring, $courses['CSE531'], $hoda, 30, [[Weekday::Tuesday, '09:00', '11:00']]);
        $springCse512 = $this->offering($spring, $courses['CSE512'], $r04, 30, [[Weekday::Wednesday, '09:00', '11:00']]);
        // Breadth over the anchor programs: shared prep + a PhD core, taught but unrostered.
        $this->offering($spring, $courses['DEP501'], $hoda, 40, [[Weekday::Sunday, '09:00', '11:00']]);
        $this->offering($spring, $courses['CSE611'], $hoda, 20, [[Weekday::Thursday, '09:00', '11:00']]);

        // Fall: the registration-open term. CSE532 is deliberately NOT offered — the headline
        // smoke's administrator creates that offering fresh (the Admissions-seeder precedent of
        // reserving demo data for the smoke).
        $fallDep501 = $this->offering($fall, $courses['DEP501'], $hoda, 40, [[Weekday::Sunday, '09:00', '11:00']]);
        $fallCse511 = $this->offering($fall, $courses['CSE511'], $r04, 30, [[Weekday::Monday, '09:00', '11:00']]);
        $fallCse512 = $this->offering($fall, $courses['CSE512'], $hoda, 30, [[Weekday::Tuesday, '09:00', '11:00']]);
        $this->offering($fall, $courses['CSE531'], $hoda, 30, [[Weekday::Wednesday, '09:00', '11:00']]);
        $this->offering($fall, $courses['CSE571'], $hoda, 20, [[Weekday::Thursday, '09:00', '11:00']]);

        // Every Spring offering gets its Class Sessions generated across the term (the same
        // pure SessionSchedule seam the register endpoint uses), so the registers exist on
        // seed day without an API round-trip.
        foreach (CourseOffering::query()->where('semester_id', $spring->getKey())->with('meetings')->get() as $offering) {
            $this->generateSessions($offering, $spring);
        }

        // ── The Spring 2026 cohort (enrollment term "Spring 2026") ───────────────────────
        $C = EnrollmentClassification::class;
        $M = RegistrationMode::class;
        $S = CourseStatus::class;

        // Sara — audits a Core course and sits below 75% attendance (the demo at-risk chip).
        $sara = $this->student('student.audit@seed.ejust.test', 'Sara Auda', 'سارة عودة', 'Female', $advisor, $program, 'Spring 2026', $adam->admissionCycleId, '31000000000001');
        $this->enrollViaApprovedRequest($sara->studentId, $advisor->getKey(), $spring, [
            ['offering' => $springCse511, 'mode' => $M::Audit, 'class' => $C::Core, 'status' => $S::Audit, 'ch' => 3],
        ]);

        // Tarek — a for-credit Spring CSE511, and CSE511 is offered again in Fall, so a Fall
        // registration of it derives a Retake (FR-REG-006). A full-attendance register row.
        $tarek = $this->student('student.retake@seed.ejust.test', 'Tarek Ratib', 'طارق راتب', 'Male', $advisor, $program, 'Spring 2026', $adam->admissionCycleId, '31000000000002');
        $this->enrollViaApprovedRequest($tarek->studentId, $advisor->getKey(), $spring, [
            ['offering' => $springCse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // Youssef — withdrew (W) from a running Spring course; the permanent record shows on
        // his schedule and he drops off the active roster.
        $youssef = $this->student('student.withdrawn@seed.ejust.test', 'Youssef Wadie', 'يوسف وديع', 'Male', $advisor, $program, 'Spring 2026', $adam->admissionCycleId, '31000000000003');
        $this->enrollViaApprovedRequest($youssef->studentId, $advisor->getKey(), $spring, [
            ['offering' => $springCse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Withdrawn, 'ch' => 3],
        ]);

        // Layla — the headline smoke's Student: a running Spring schedule to withdraw from
        // (CSE531, Regular) and an audited Spring course the instructor drives to at-risk
        // (CSE512, r04's offering). No Fall request yet — the smoke submits one.
        $layla = $this->student('student.smoke@seed.ejust.test', 'Layla Samir', 'ليلى سمير', 'Female', $advisor, $program, 'Spring 2026', $adam->admissionCycleId, '31000000000004');
        $this->enrollViaApprovedRequest($layla->studentId, $advisor->getKey(), $spring, [
            ['offering' => $springCse531, 'mode' => $M::Regular, 'class' => $C::InternalElective, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $springCse512, 'mode' => $M::Audit, 'class' => $C::Core, 'status' => $S::Audit, 'ch' => 3],
        ]);

        // ── Attendance history on the Spring CSE511 register (Dr. Hoda's) ────────────────
        // Four early sessions: Tarek present throughout (100%), Sara present twice then absent
        // twice (2/4 = 50%) — below the 75% audit minimum, so her AU enrollment is at risk.
        $this->recordAttendance($springCse511, $tarek->studentId, [true, true, true, true]);
        $this->recordAttendance($springCse511, $sara->studentId, [true, true, false, false]);

        // ── The reused Phase-2 Student (Adam) — a Fall 2026 first-semester registration ──
        // Extends the accepted applicant into Phase 3: his advisor-approved initial schedule.
        $this->enrollViaApprovedRequest($adam->studentId, $advisor->getKey(), $fall, [
            ['offering' => $fallDep501, 'mode' => $M::Regular, 'class' => $C::Preparatory, 'status' => $S::Regular, 'ch' => 1],
            ['offering' => $fallCse511, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
            ['offering' => $fallCse512, 'mode' => $M::Regular, 'class' => $C::Core, 'status' => $S::Regular, 'ch' => 3],
        ]);

        // ── Nadia — a Pending Fall request resting in the Advisor's queue ────────────────
        $nadia = $this->student('student.pending@seed.ejust.test', 'Nadia Bondok', 'نادية بندق', 'Female', $advisor, $program, 'Fall 2026', $adam->admissionCycleId, '31000000000005');
        $this->pendingRequest($nadia->studentId, $fall, [
            ['offering' => $fallCse511, 'mode' => $M::Regular],
            ['offering' => $fallCse512, 'mode' => $M::Regular],
        ]);
    }

    /** Spring 2026 — running now and inside its self-serve withdrawal window (evergreen). */
    private function springSemester(): Semester
    {
        return $this->semester(Term::Spring, 2026, [
            'registration_opens_at' => '-10 weeks',
            'registration_closes_at' => '-8 weeks',
            'starts_at' => '-6 weeks',
            'add_drop_ends_at' => '-4 weeks',
            'late_add_ends_at' => '-3 weeks',
            'withdrawal_deadline_at' => '+2 weeks',
            'ends_at' => '+6 weeks',
            'grade_submission_deadline_at' => '+8 weeks',
        ]);
    }

    /** Fall 2026 — its registration window open now, the term still ahead (evergreen). */
    private function fallSemester(): Semester
    {
        return $this->semester(Term::Fall, 2026, [
            'registration_opens_at' => '-1 week',
            'registration_closes_at' => '+3 weeks',
            'starts_at' => '+5 weeks',
            'add_drop_ends_at' => '+7 weeks',
            'late_add_ends_at' => '+8 weeks',
            'withdrawal_deadline_at' => '+13 weeks',
            'ends_at' => '+18 weeks',
            'grade_submission_deadline_at' => '+20 weeks',
        ]);
    }

    /**
     * A Semester keyed by (term, year) so re-seeding never trips the unique constraint; the
     * period windows are relative offsets from `now()` so the demo stays evergreen.
     *
     * @param  array<string, string>  $offsets  column => relative modifier
     */
    private function semester(Term $term, int $year, array $offsets): Semester
    {
        return Semester::firstOrCreate(
            ['term' => $term->value, 'year' => $year],
            array_map(fn (string $modifier): Carbon => Carbon::now()->modify($modifier), $offsets),
        );
    }

    /**
     * A Course Offering keyed by (semester, course), with its weekly meeting rows. The three
     * references (semester, course, instructor) are opaque ids — no cross-module FK (ADR-0012).
     *
     * @param  list<array{0: Weekday, 1: string, 2: string}>  $meetings  [weekday, start, end]
     */
    private function offering(Semester $semester, Course $course, User $instructor, ?int $capacity, array $meetings): CourseOffering
    {
        $offering = CourseOffering::firstOrCreate(
            ['semester_id' => $semester->getKey(), 'course_id' => $course->getKey()],
            ['instructor_id' => $instructor->getKey(), 'capacity' => $capacity],
        );

        if ($offering->meetings()->doesntExist()) {
            foreach ($meetings as [$weekday, $startsAt, $endsAt]) {
                $offering->meetings()->create(['weekday' => $weekday, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
            }
        }

        return $offering;
    }

    /**
     * Generate the Offering's Class Sessions across its Semester's dates via the pure
     * {@see SessionSchedule} seam, persisted idempotently on the (offering, date, start) key —
     * exactly the shape the register endpoint produces when an Instructor opens it.
     */
    private function generateSessions(CourseOffering $offering, Semester $semester): void
    {
        $meetings = $offering->meetings->map(fn (OfferingMeeting $meeting): array => $meeting->toSlotArray())->all();

        foreach (SessionSchedule::generate($semester->starts_at, $semester->ends_at, $meetings) as $descriptor) {
            ClassSession::firstOrCreate(
                [
                    'course_offering_id' => $offering->getKey(),
                    'session_date' => $descriptor['date'],
                    'starts_at' => $descriptor['starts_at'],
                ],
                ['ends_at' => $descriptor['ends_at'], 'cancelled_at' => null],
            );
        }
    }

    /**
     * Mint a Student through the Identity\Public seam (ADR-0013) — never a raw row — reusing
     * an existing one on re-seed (idempotent by email + a live Student anchor). The advisor
     * (already R03) is the enrolling advisor; identity mirrors the Admissions snapshot shape.
     */
    private function student(
        string $email,
        string $nameEn,
        string $nameAr,
        string $gender,
        User $advisor,
        Program $program,
        string $enrollmentTerm,
        int $admissionCycleId,
        string $nationalId,
    ): StudentSummary {
        $directory = app(StudentDirectory::class);

        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            $existing = $directory->find((int) $user->getKey());
            if ($existing !== null) {
                return $existing; // already minted on a previous run.
            }
        } else {
            $user = User::create([
                'email' => $email,
                'name' => $nameEn,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => Carbon::now(),
            ]);
        }
        $user->assignRole(Role::Applicant);

        $result = app(StudentEnrollment::class)->admit(new AdmitStudent(
            userId: (int) $user->getKey(),
            actorUserId: null, // a seeded mint has no human actor.
            programId: (int) $program->getKey(),
            programCode: $program->code,
            admissionYear: 2026,
            enrollmentTerm: $enrollmentTerm,
            admissionCycleId: $admissionCycleId,
            advisorId: (int) $advisor->getKey(),
            identity: new StudentIdentity(
                nameEn: $nameEn,
                nameAr: $nameAr,
                nationalId: $nationalId,
                dateOfBirth: '1997-03-15',
                nationality: 'EG',
                gender: $gender,
            ),
        ));

        return $directory->findByStudentId($result->studentId) ?? $directory->find((int) $user->getKey());
    }

    /**
     * Reproduce the advisor accept→mint outcome (ADR-0013/0017): an Approved Initial request
     * with each line stamped approved, and one snapshotting Enrollment per line. Guarded on an
     * existing Approved Initial request for the (student, semester), so re-seeding is a no-op.
     *
     * @param  list<array{offering: CourseOffering, mode: RegistrationMode, class: EnrollmentClassification, status: CourseStatus, ch: int}>  $entries
     */
    private function enrollViaApprovedRequest(int $studentId, int $advisorId, Semester $semester, array $entries): void
    {
        $already = RegistrationRequest::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semester->getKey())
            ->where('kind', RegistrationKind::Initial)
            ->where('status', RegistrationStatus::Approved)
            ->exists();
        if ($already) {
            return;
        }

        $request = RegistrationRequest::create([
            'student_id' => $studentId,
            'semester_id' => $semester->getKey(),
            'kind' => RegistrationKind::Initial,
            'status' => RegistrationStatus::Approved,
            'advisor_note' => null,
            'decided_at' => Carbon::now()->subWeeks(5),
            'decided_by' => $advisorId,
        ]);

        foreach ($entries as $entry) {
            $request->lines()->create([
                'course_offering_id' => $entry['offering']->getKey(),
                'mode' => $entry['mode'],
                'decision' => LineDecision::Approved,
            ]);

            Enrollment::firstOrCreate(
                ['student_id' => $studentId, 'course_offering_id' => $entry['offering']->getKey()],
                [
                    'classification' => $entry['class'],
                    'course_status' => $entry['status'],
                    'credit_hours' => $entry['ch'],
                ],
            );
        }
    }

    /**
     * A Pending Initial request resting in the Advisor's queue (idempotent — one Pending per
     * student+semester). Backdated a couple of days so the queue's oldest-first order reads
     * sensibly on seed day.
     *
     * @param  list<array{offering: CourseOffering, mode: RegistrationMode}>  $lines
     */
    private function pendingRequest(int $studentId, Semester $semester, array $lines): void
    {
        if (RegistrationRequest::hasPendingFor($studentId, (int) $semester->getKey())) {
            return;
        }

        $request = RegistrationRequest::create([
            'student_id' => $studentId,
            'semester_id' => $semester->getKey(),
            'kind' => RegistrationKind::Initial,
            'status' => RegistrationStatus::Pending,
        ]);
        $request->forceFill(['created_at' => Carbon::now()->subDays(2)])->save();

        foreach ($lines as $line) {
            $request->lines()->create([
                'course_offering_id' => $line['offering']->getKey(),
                'mode' => $line['mode'],
                'decision' => null,
            ]);
        }
    }

    /**
     * Record a present/absent run for one Student's Enrollment across the Offering's earliest
     * sessions — one binary mark per session, upserted on (session, enrollment) so re-seeding
     * corrects rather than duplicates. The derived percentage (present ÷ recorded) drives the
     * register's audit-at-risk chip; nothing is stored but the marks.
     *
     * @param  list<bool>  $present  present flags in session-date order
     */
    private function recordAttendance(CourseOffering $offering, int $studentId, array $present): void
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $offering->getKey())
            ->first();
        if ($enrollment === null) {
            return;
        }

        $sessions = ClassSession::query()
            ->where('course_offering_id', $offering->getKey())
            ->counted()
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->take(count($present))
            ->get();

        foreach ($sessions as $index => $session) {
            AttendanceMark::updateOrCreate(
                ['class_session_id' => $session->getKey(), 'enrollment_id' => $enrollment->getKey()],
                ['present' => $present[$index]],
            );
        }
    }
}
