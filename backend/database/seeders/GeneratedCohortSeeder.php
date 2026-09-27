<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsAcademicWorld;
use Database\Seeders\Concerns\SeedsGradedHistory;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/** Generated layer; natural keys and isolated staff leave named persona footprints intact. */
class GeneratedCohortSeeder extends Seeder
{
    use SeedsAcademicWorld;
    use SeedsGradedHistory;

    public function run(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(4607);
        $password = Hash::make('password');
        $advisor = $this->staff('cohort.advisor@seed.ejust.test', Role::Advisor, $password);
        $instructor = $this->staff('cohort.instructor@seed.ejust.test', Role::Instructor, $password);
        $reviewer = $this->staff('cohort.head@seed.ejust.test', Role::DepartmentHead, $password);
        $cycle = (int) AdmissionCycle::query()->where('intake_term', 'Fall')->where('year', 2026)->firstOrFail()->id;
        $programs = Program::query()->with('curriculumEntries.course')->orderBy('id')->get();
        $semesters = Semester::query()->orderBy('starts_at')->get();
        $marks = [45, 55, 60, 65, 70, 75, 80, 85, 90, 97];

        // Resolve one untouched Offering per Program/Semester, never add to a named
        // Instructor's roster or Grade Sheet. Re-seeding selects our existing Offering.
        $offerings = [];
        foreach ($programs as $program) {
            foreach ($semesters as $semester) {
                foreach ($program->curriculumEntries as $entry) {
                    $course = $entry->course;
                    if ($course->level->value >= 700) {
                        continue;
                    }
                    $existing = CourseOffering::query()->where('semester_id', $semester->id)->where('course_id', $course->id)->first();
                    if ($existing !== null && $existing->instructor_id !== $instructor->id) {
                        continue;
                    }
                    $offering = $this->offering($semester, $course, $instructor, null, [[Weekday::Monday, '14:00', '16:00']]);
                    DepartmentHeadAssignment::firstOrCreate(['user_id' => $reviewer->id, 'department_id' => $course->department_id]);
                    $classification = match ($entry->category) {
                        CourseCategory::Preparatory => EnrollmentClassification::Preparatory,
                        CourseCategory::ProgramCore => EnrollmentClassification::Core,
                        CourseCategory::ResearchElective => EnrollmentClassification::InternalElective,
                    };
                    $offerings[$program->id][$semester->id] = [$offering, $classification, $course->total_ch];
                    break;
                }
            }
        }

        for ($index = 0; $index < 180; $index++) {
            $program = $programs[$index % $programs->count()];
            $semester = $semesters[intdiv($index, $programs->count()) % $semesters->count()];
            $email = sprintf('cohort.student.%03d@seed.ejust.test', $index + 1);
            $name = $faker->name();
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'email_verified_at' => now()]);
            $student = $this->student($email, $name, 'طالب مولد '.($index + 1), $index % 2 === 0 ? 'Male' : 'Female',
                $advisor, $program, $semester->term->value.' '.$semester->year, $cycle, (string) (39000000000000 + $index));
            [$offering, $classification, $hours] = $offerings[$program->id][$semester->id];
            $status = $index % 17 === 0 ? CourseStatus::Audit : ($index % 19 === 0 ? CourseStatus::Withdrawn : CourseStatus::Regular);
            $this->enrollViaApprovedRequest($student->studentId, $advisor->id, $semester, [[
                'offering' => $offering, 'mode' => $status === CourseStatus::Audit ? RegistrationMode::Audit : RegistrationMode::Regular,
                'class' => $classification, 'status' => $status, 'ch' => $hours,
            ]]);
            $enrollmentId = $this->enrollmentId($student, $offering);
            if ($status === CourseStatus::Regular && $semester->ends_at->isPast()) {
                $sheet = $this->finalizedSheet($offering, GradeSheetKind::Initial, $instructor, $reviewer, 4);
                $this->grade($sheet, $enrollmentId, $marks[$index % count($marks)]);
            }
            if ($enrollmentId !== null && $status !== CourseStatus::Withdrawn) {
                for ($week = 1; $week <= 3; $week++) {
                    $date = $semester->starts_at->copy()->addWeeks($week)->next('Monday');
                    if ($date->isFuture()) {
                        continue;
                    }
                    $session = ClassSession::firstOrCreate(['course_offering_id' => $offering->id, 'session_date' => $date->toDateString()], ['starts_at' => '14:00', 'ends_at' => '16:00']);
                    AttendanceMark::firstOrCreate(['class_session_id' => $session->id, 'enrollment_id' => $enrollmentId], ['present' => ($index + $week) % 4 !== 0]);
                }
            }
        }

        $masters = $programs->filter(fn (Program $program): bool => $program->degree_level->value === 'MSc')->values();
        $photoPath = 'application-documents/seed/cohort-photo.png';
        if (! Storage::disk('local')->exists($photoPath)) {
            Storage::disk('local')->put($photoPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jE+QAAAAASUVORK5CYII='));
        }
        for ($index = 0; $index < 40; $index++) {
            $name = 'Cohort Applicant '.$faker->lastName().' '.($index + 1);
            $email = sprintf('cohort.applicant.%03d@seed.ejust.test', $index + 1);
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'email_verified_at' => now()]);
            $user->assignRole(Role::Applicant);
            if (Application::query()->where('user_id', $user->id)->where('admission_cycle_id', $cycle)->exists()) {
                continue;
            }
            $submittedAt = now()->subDays($index + 1);
            $application = Application::factory()->create([
                'user_id' => $user->id, 'admission_cycle_id' => $cycle,
                'status' => ApplicationStatus::UnderSAReview,
                'name_en' => $name, 'name_ar' => 'أحمد المتقدم '.($index + 1),
                'national_id' => (string) (38000000000000 + $index), 'id_type' => 'national_id',
                'phone' => '+201000000000', 'date_of_birth' => '1998-05-20', 'nationality' => 'EG', 'gender' => 'Male',
                'address_country' => 'Egypt', 'address_city' => 'Alexandria', 'address_street' => '15 Corniche Road',
                'previous_university' => 'Cairo University', 'graduation_certificate_name' => 'BSc in Computer Science',
                'graduation_date' => '2020-07-01', 'previous_degree' => 'BSc', 'previous_major' => 'Computer Science',
                'graduation_gpa' => 3.5, 'graduation_cgpa' => 3.5, 'gpa_scale' => '4.0', 'is_ejust_graduate' => false,
                'applying_for_degree' => 'MSc', 'english_test' => 'IELTS', 'english_score' => 7.5, 'native_arabic_speaker' => true,
                'primary_program_id' => $masters[$index % $masters->count()]->id,
                'submitted_at' => $submittedAt, 'finalized_at' => $submittedAt,
            ]);
            foreach ([[ApplicationStatus::Draft, ApplicationStatus::Submitted], [ApplicationStatus::Submitted, ApplicationStatus::EmailVerified], [ApplicationStatus::EmailVerified, ApplicationStatus::UnderSAReview]] as [$from, $to]) {
                $application->statusTransitions()->create(['from_status' => $from, 'to_status' => $to, 'actor_user_id' => $user->id, 'occurred_at' => $submittedAt]);
            }
            foreach ([DocumentType::GraduationCertificate, DocumentType::Transcript, DocumentType::NationalId, DocumentType::Photo, DocumentType::EnglishCertificate] as $type) {
                $photo = $type === DocumentType::Photo;
                $path = $photo ? $photoPath : 'application-documents/seed/sample-document.pdf';
                $application->documents()->create([
                    'document_type' => $type, 'original_filename' => $type->value.($photo ? '.png' : '.pdf'),
                    'stored_path' => $path, 'mime' => $photo ? 'image/png' : 'application/pdf',
                    'size_bytes' => Storage::disk('local')->size($path),
                ]);
            }
        }
    }

    private function staff(string $email, Role $role, string $password): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => 'Generated '.$role->label(), 'password' => $password, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}
