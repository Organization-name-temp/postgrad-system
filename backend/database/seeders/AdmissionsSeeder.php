<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Enums\InterviewMode;
use Modules\Admissions\Internal\Enums\InterviewStatus;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Events\AdmissionAccepted;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentIdentity;

/**
 * Seeds a realistic, deterministic Admissions demo (spec §Seeder, ticket 12) so every
 * Phase-2 screen has data the moment the app boots: an open **Fall 2026** cycle (plus a
 * closed past one), a Dean for each Faculty with their `faculty_dean_assignments`, a
 * pool of advisors and interviewers, one Application **resting in each visible status**
 * (Draft … Rejected) with a populated status timeline and uploaded documents, booked and
 * open interview slots, and — for the `Accepted` one — a really-minted `Student` (a
 * University ID, R01→R02, an advisor) through the same Identity\Public seam the live
 * accept uses (ADR-0013).
 *
 * Idempotent (keyed by the seeded applicants' emails), so re-running `db:seed` is safe.
 * It deliberately leaves the demo applicant's email / national id free — the headline
 * Playwright smoke registers its own fresh applicant into the open cycle.
 */
class AdmissionsSeeder extends Seeder
{
    /** The shared dev password every seeded account uses (as the role users do). */
    private const PASSWORD = 'password';

    /** Where the shared sample document bytes live on the private disk. */
    private const SAMPLE_DOCUMENT_PATH = 'application-documents/seed/sample-document.pdf';

    public function run(): void
    {
        $openCycle = $this->openCycle();
        $this->closedCycle();

        $foe = Faculty::query()->where('code', 'FoE')->firstOrFail();
        $program = Program::query()->where('code', 'MSC-CE')->firstOrFail();

        // A Dean per Faculty with their routing rows; the canonical R07 Dean covers FoE
        // (where the demo pipeline lives), so the headline smoke can sign in as r07.
        $foeDean = $this->deanFor($foe->getKey(), 'r07@ejust.test');
        foreach (['BAS' => 'dean.bas@ejust.test', 'CSIT' => 'dean.csit@ejust.test'] as $code => $email) {
            $faculty = Faculty::query()->where('code', $code)->first();
            if ($faculty !== null) {
                $this->deanFor($faculty->getKey(), $email);
            }
        }

        // A small pool so least-loaded-advisor selection has real choices, and an
        // interviewer for the seeded/open slots.
        $advisor = $this->staff('advisor.amina@ejust.test', 'Dr. Amina Farouk', 'د. أمينة فاروق', Role::Advisor);
        $this->staff('advisor.karim@ejust.test', 'Dr. Karim Nabil', 'د. كريم نبيل', Role::Advisor);
        $this->staff('advisor.laila@ejust.test', 'Dr. Laila Sami', 'د. ليلى سامي', Role::Advisor);
        $interviewer = $this->staff('interviewer.hoda@ejust.test', 'Dr. Hoda Mansour', 'د. هدى منصور', Role::Instructor);
        $interviewer2 = $this->staff('interviewer.omar@ejust.test', 'Dr. Omar Zaki', 'د. عمر زكي', Role::Instructor);

        $sa = User::query()->where('email', 'r05@ejust.test')->first();

        $this->sampleDocument();
        $this->applications($openCycle, $program, $foeDean, $sa, $advisor, $interviewer, $interviewer2);
        $this->openSlots($interviewer);
    }

    /** The open Fall 2026 cycle — its window spans the present, so it is currently open. */
    private function openCycle(): AdmissionCycle
    {
        return AdmissionCycle::firstOrCreate(
            ['intake_term' => IntakeTerm::Fall->value, 'year' => 2026],
            [
                'name_en' => 'Fall 2026',
                'name_ar' => 'خريف 2026',
                'opens_at' => Carbon::now()->subMonth(),
                'closes_at' => Carbon::now()->addMonths(2),
                'is_active' => true,
            ],
        );
    }

    /** A closed past cycle (Fall 2025), to demonstrate the closed-window gate + history. */
    private function closedCycle(): void
    {
        AdmissionCycle::firstOrCreate(
            ['intake_term' => IntakeTerm::Fall->value, 'year' => 2025],
            [
                'name_en' => 'Fall 2025',
                'name_ar' => 'خريف 2025',
                'opens_at' => Carbon::now()->subYear(),
                'closes_at' => Carbon::now()->subMonths(9),
                'is_active' => true,
            ],
        );
    }

    /** Ensure the user exists with the given role, returning it (idempotent by email). */
    private function staff(string $email, string $name, string $nameAr, Role $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => Carbon::now()],
        );
        $user->assignRole($role);

        return $user;
    }

    /** A Dean (R07) assigned to the given opaque Faculty id (idempotent). */
    private function deanFor(int $facultyId, string $email): User
    {
        $dean = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Dean of Faculty', 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => Carbon::now()],
        );
        $dean->assignRole(Role::Dean);
        FacultyDeanAssignment::firstOrCreate(['user_id' => $dean->getKey(), 'faculty_id' => $facultyId]);

        return $dean;
    }

    /** Write a tiny valid PDF to the private disk that every seeded document references. */
    private function sampleDocument(): void
    {
        if (! Storage::disk('local')->exists(self::SAMPLE_DOCUMENT_PATH)) {
            Storage::disk('local')->put(
                self::SAMPLE_DOCUMENT_PATH,
                "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n",
            );
        }
    }

    /**
     * One Application resting in each visible status. `sa_approved` is transient (the
     * approve action passes straight through it), so nothing rests there; the other
     * twelve statuses each get a deterministic applicant, a full status timeline, and —
     * from finalize onward — uploaded documents.
     */
    private function applications(
        AdmissionCycle $cycle,
        Program $program,
        User $dean,
        ?User $sa,
        User $advisor,
        User $interviewer,
        User $interviewer2,
    ): void {
        $saId = $sa?->getKey();
        $nid = 30000000000000; // seeded national ids occupy 3000000000000X — never the smoke's.

        $specs = [
            [ApplicationStatus::Draft, 'Dana Draft', 'دانة درويش', Gender::Female],
            [ApplicationStatus::Submitted, 'Sami Submitted', 'سامي صبري', Gender::Male],
            [ApplicationStatus::EmailVerified, 'Nour Verified', 'نور ناجي', Gender::Female],
            [ApplicationStatus::UnderSAReview, 'Rana Review', 'رنا رأفت', Gender::Female],
            [ApplicationStatus::ReturnedForRevision, 'Ramy Returned', 'رامي رشاد', Gender::Male],
            [ApplicationStatus::UnderDeanReview, 'Dina Dean', 'دينا داود', Gender::Female],
            [ApplicationStatus::InterviewScheduled, 'Sherif Slot', 'شريف سعيد', Gender::Male],
            [ApplicationStatus::InterviewCompleted, 'Iman Interview', 'إيمان عماد', Gender::Female],
            [ApplicationStatus::Accepted, 'Adam Accepted', 'آدم عاطف', Gender::Male],
            [ApplicationStatus::ConditionallyAccepted, 'Camelia Conditional', 'كاميليا كمال', Gender::Female],
            [ApplicationStatus::Waitlisted, 'Wael Waitlist', 'وائل وحيد', Gender::Male],
            [ApplicationStatus::Rejected, 'Rami Rejected', 'رامز رزق', Gender::Male],
        ];

        foreach ($specs as $index => [$status, $nameEn, $nameAr, $gender]) {
            $email = 'applicant.'.$status->value.'@seed.ejust.test';
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nameEn, 'password' => Hash::make(self::PASSWORD)],
            );

            // Already seeded on a previous run — leave it untouched (idempotent).
            if (! $user->wasRecentlyCreated) {
                continue;
            }

            $verified = $status !== ApplicationStatus::Draft && $status !== ApplicationStatus::Submitted;
            if ($verified) {
                $user->forceFill(['email_verified_at' => Carbon::now()])->save();
            }
            $user->assignRole(Role::Applicant);

            $application = Application::factory()->create(array_merge(
                $this->snapshot($nameEn, $nameAr, (string) ($nid + $index + 1), $gender),
                [
                    'user_id' => $user->getKey(),
                    'admission_cycle_id' => $cycle->getKey(),
                    'status' => $status,
                    'primary_program_id' => $program->getKey(),
                    'submitted_at' => $status === ApplicationStatus::Draft ? null : Carbon::now()->subDays(9),
                    'finalized_at' => $this->isFinalized($status) ? Carbon::now()->subDays(8) : null,
                    'conditions' => $status === ApplicationStatus::ConditionallyAccepted
                        ? 'Submit the final, sealed transcript by 1 September 2026.'
                        : null,
                    'waitlist_position' => $status === ApplicationStatus::Waitlisted ? 2 : null,
                ],
            ));

            $this->recordTimeline($application, $status, $user->getKey(), $saId, $dean->getKey());

            if ($this->isFinalized($status)) {
                $this->attachDocuments($application);
            }

            if ($status === ApplicationStatus::InterviewScheduled) {
                $this->bookInterview($application, $interviewer, completed: false);
            } elseif ($status === ApplicationStatus::InterviewCompleted) {
                // A different interviewer, so both seeded interviewers have real work.
                $this->bookInterview($application, $interviewer2, completed: true);
            } elseif ($status === ApplicationStatus::Accepted) {
                $this->mintStudent($application, $cycle, $program, $dean->getKey(), $advisor->getKey(), $nameEn, $nameAr, (string) ($nid + $index + 1), $gender);
            }
        }
    }

    /** A complete, deterministic Step-1 personal-info snapshot (a qualifying BSc→MSc). */
    private function snapshot(string $nameEn, string $nameAr, string $nationalId, Gender $gender): array
    {
        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'national_id' => $nationalId,
            'phone' => '+201000000000',
            'date_of_birth' => '1998-05-20',
            'nationality' => 'Egyptian',
            'gender' => $gender,
            'address_country' => 'Egypt',
            'address_city' => 'Alexandria',
            'address_street' => '15 Corniche Road',
            'previous_university' => 'Cairo University',
            'graduation_certificate_name' => 'BSc in Computer Science',
            'graduation_date' => '2020-07-01',
            'previous_degree' => PreviousDegree::BSc,
            'previous_major' => 'Computer Science',
            'graduation_gpa' => 3.50,
            'graduation_cgpa' => 3.45,
            'gpa_scale' => GpaScale::FourPointZero,
            'is_ejust_graduate' => false,
            'applying_for_degree' => DegreeLevel::MSc,
            'english_test' => EnglishTest::Ielts,
            'english_score' => 7.50,
            'native_arabic_speaker' => true,
        ];
    }

    /** Whether the status is at/after finalize (documents locked, in review or beyond). */
    private function isFinalized(ApplicationStatus $status): bool
    {
        return ! in_array($status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Submitted,
            ApplicationStatus::EmailVerified,
        ], true);
    }

    /**
     * Append the append-only status timeline leading to the current status — the same
     * sequence the real transition methods would have written — backdated so the entry
     * into the current status is a couple of days old (so queue aging reads sensibly).
     */
    private function recordTimeline(
        Application $application,
        ApplicationStatus $status,
        int $applicantId,
        ?int $saId,
        int $deanId,
    ): void {
        $S = ApplicationStatus::class;
        // The happy path, each step tagged with its actor id (null = the automatic
        // SAApproved → UnderDeanReview forward).
        $happy = [
            [$S::Draft, $S::Submitted, $applicantId],
            [$S::Submitted, $S::EmailVerified, $applicantId],
            [$S::EmailVerified, $S::UnderSAReview, $applicantId],
            [$S::UnderSAReview, $S::SAApproved, $saId],
            [$S::SAApproved, $S::UnderDeanReview, null],
            [$S::UnderDeanReview, $S::InterviewScheduled, $deanId],
            [$S::InterviewScheduled, $S::InterviewCompleted, $saId],
        ];

        // How far along the happy path the status sits, and any branch step off it.
        [$prefix, $branch] = match ($status) {
            $S::Draft => [0, null],
            $S::Submitted => [1, null],
            $S::EmailVerified => [2, null],
            $S::UnderSAReview => [3, null],
            $S::ReturnedForRevision => [3, [$S::UnderSAReview, $S::ReturnedForRevision, $saId]],
            $S::UnderDeanReview => [5, null],
            $S::Rejected => [5, [$S::UnderDeanReview, $S::Rejected, $deanId]],
            $S::InterviewScheduled => [6, null],
            $S::InterviewCompleted => [7, null],
            $S::Accepted => [7, [$S::InterviewCompleted, $S::Accepted, $deanId]],
            $S::ConditionallyAccepted => [7, [$S::InterviewCompleted, $S::ConditionallyAccepted, $deanId]],
            $S::Waitlisted => [7, [$S::InterviewCompleted, $S::Waitlisted, $deanId]],
            default => [0, null],
        };

        $steps = array_slice($happy, 0, $prefix);
        if ($branch !== null) {
            $steps[] = $branch;
        }
        if ($steps === []) {
            return; // A Draft has no history yet.
        }

        $base = Carbon::now()->subDays(count($steps) + 1);
        foreach ($steps as $i => [$from, $to, $actorId]) {
            $note = match (true) {
                $to === $S::ReturnedForRevision => 'Please re-upload a clearer transcript scan.',
                $to === $S::ConditionallyAccepted => 'Submit the final, sealed transcript by 1 September 2026.',
                $to === $S::Rejected => 'The committee did not recommend admission this cycle.',
                default => null,
            };

            $application->statusTransitions()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_user_id' => $actorId,
                'note' => $note,
                'occurred_at' => $base->copy()->addDays($i),
            ]);
        }
    }

    /** The standard uploaded document set for a finalized application (BR-66/67). */
    private function attachDocuments(Application $application): void
    {
        $types = [
            [DocumentType::GraduationCertificate, 'graduation-certificate.pdf'],
            [DocumentType::Transcript, 'transcript.pdf'],
            [DocumentType::NationalId, 'national-id.pdf'],
            [DocumentType::Photo, 'photo.pdf'],
            [DocumentType::EnglishCertificate, 'ielts.pdf'],
        ];

        foreach ($types as [$type, $filename]) {
            $application->documents()->create([
                'document_type' => $type,
                'original_filename' => $filename,
                'stored_path' => self::SAMPLE_DOCUMENT_PATH,
                'mime' => 'application/pdf',
                'size_bytes' => 64,
            ]);
        }
    }

    /** Book the application's interview into a fresh slot; optionally mark it completed. */
    private function bookInterview(Application $application, User $interviewer, bool $completed): void
    {
        $slot = InterviewSlot::factory()->create([
            'scheduled_at' => $completed ? Carbon::now()->subDay()->setTime(10, 0) : Carbon::now()->addDays(2)->setTime(10, 0),
            'duration_minutes' => 30,
            'interviewer_id' => $interviewer->getKey(),
            'mode' => InterviewMode::InPerson,
            'location' => 'Building C, Room 204',
            'meeting_link' => null,
        ]);

        Interview::factory()->create([
            'application_id' => $application->getKey(),
            'interview_slot_id' => $slot->getKey(),
            'status' => InterviewStatus::Confirmed,
            'reschedule_count' => 0,
            'evaluation_score' => $completed ? 8 : null,
            'evaluation_notes' => $completed ? 'Strong research fit; clear motivation.' : null,
            'completed_at' => $completed ? Carbon::now()->subDay()->setTime(10, 30) : null,
        ]);
    }

    /**
     * Mint a real Student for the accepted applicant through the Identity\Public seam —
     * the same call the live accept transaction makes (ADR-0013) — so the deep anchor
     * has a genuine University ID, R01→R02, an advisor, and an audit entry, and the
     * acceptance notification lands in the applicant's bell.
     */
    private function mintStudent(
        Application $application,
        AdmissionCycle $cycle,
        Program $program,
        int $deanId,
        int $advisorId,
        string $nameEn,
        string $nameAr,
        string $nationalId,
        Gender $gender,
    ): void {
        $result = app(StudentEnrollment::class)->admit(new AdmitStudent(
            userId: (int) $application->user_id,
            actorUserId: $deanId,
            programId: $program->getKey(),
            programCode: $program->code,
            admissionYear: (int) $cycle->year,
            enrollmentTerm: $cycle->intake_term->value.' '.$cycle->year,
            admissionCycleId: $cycle->getKey(),
            advisorId: $advisorId,
            identity: new StudentIdentity(
                nameEn: $nameEn,
                nameAr: $nameAr,
                nationalId: $nationalId,
                dateOfBirth: '1998-05-20',
                nationality: 'Egyptian',
                gender: $gender->value,
            ),
        ));

        Event::dispatch(new AdmissionAccepted(
            userId: (int) $application->user_id,
            applicationId: (int) $application->getKey(),
            universityId: $result->universityId,
        ));
    }

    /** A few spare open (unbooked) slots so scheduling can be demoed and the smoke can book. */
    private function openSlots(User $interviewer): void
    {
        // Only the first seeding lays these down; a natural key would drift with `now()`.
        if (InterviewSlot::query()->whereDoesntHave('interview')->exists()) {
            return;
        }

        foreach ([3, 4, 5] as $daysOut) {
            InterviewSlot::factory()->create([
                'scheduled_at' => Carbon::now()->addDays($daysOut)->setTime(11, 0),
                'duration_minutes' => 30,
                'interviewer_id' => $interviewer->getKey(),
                'mode' => InterviewMode::InPerson,
                'location' => 'Building C, Room 204',
                'meeting_link' => null,
            ]);
        }
    }
}
