<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentIdentity;
use Modules\Identity\Public\StudentSummary;

/**
 * The four world-building acts every academic-phase seeder performs — a Semester, a Course
 * Offering, a minted Student, and the advisor-approved registration that turns the last two
 * into Enrollments. Extracted when `GradingSeeder` (phase 4) needed exactly what
 * `EnrollmentSeeder` (phase 3) already did, so the two demo worlds cannot drift in *how* they
 * are built while differing in what they contain.
 *
 * Every helper is **idempotent by natural key** — (term, year), (semester, course), the
 * Student's email, and one Approved Initial request per (student, semester) — which is what
 * makes `db:seed` safe to re-run. Semester windows are always **relative offsets from seed
 * time** rather than fixed dates, so both demo worlds stay evergreen.
 */
trait SeedsAcademicWorld
{
    /** The shared dev password every seeded account uses (as the role users do). */
    private const SEED_PASSWORD = 'password';

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
                'password' => Hash::make(self::SEED_PASSWORD),
                'email_verified_at' => Carbon::now(),
            ]);
        }
        $user->assignRole(Role::Applicant);

        $admittedAt = Carbon::now()->toImmutable();
        $curriculumVersion = app(ProgramCatalog::class)
            ->curriculumVersionEffectiveAt((int) $program->getKey(), $admittedAt);
        if ($curriculumVersion === null) {
            throw new \RuntimeException("Seed Program {$program->code} has no Curriculum Version.");
        }

        $result = app(StudentEnrollment::class)->admit(new AdmitStudent(
            userId: (int) $user->getKey(),
            actorUserId: null, // a seeded mint has no human actor.
            programId: (int) $program->getKey(),
            programCode: $program->code,
            curriculumVersionId: $curriculumVersion->id,
            admissionYear: 2026,
            enrollmentTerm: $enrollmentTerm,
            admissionCycleId: $admissionCycleId,
            advisorId: (int) $advisor->getKey(),
            admittedAt: $admittedAt,
            identity: new StudentIdentity(
                nameEn: $nameEn,
                nameAr: $nameAr,
                nationalId: $nationalId,
                dateOfBirth: '1997-03-15',
                nationality: 'EG',
                gender: $gender,
                identificationType: 'national_id', // Explicit kind of this fictional seed identity.
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
     * sensibly on seed day, and so an Advisee Requests Dashboard Card has a real age to show.
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
}
