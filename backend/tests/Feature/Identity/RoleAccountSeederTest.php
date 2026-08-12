<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;

// The canonical role accounts carry data now (phase 4.5, ticket 02), and the Dashboard is made
// of counts — so an account quietly reverting to empty would not fail a single other test while
// making the phase's headline demo unshowable. These assertions are that alarm. They read the
// seeded world through the same facades and HTTP surfaces the app reads it through, never by
// re-deriving anything here.
//
// The full DatabaseSeeder runs because RoleAccountSeeder deliberately builds on top of the
// Catalog, Admissions, Enrollment and Grading worlds; the tests are therefore few and dense.

uses(RefreshDatabase::class);

/** The signed-in user behind one canonical role account. */
function rasUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

it('makes r02@ a real student whose CGPA and latest semester GPA are both computable', function () {
    $this->seed(DatabaseSeeder::class);

    $user = rasUser('r02@ejust.test');
    $student = app(StudentDirectory::class)->find((int) $user->getKey());

    // Minted through the acceptance seam, so the role swap left the canonical role set intact:
    // still exactly R02, never carrying the Applicant role the mint borrows on the way in.
    expect($student)->not->toBeNull();
    expect($user->refresh()->roles()->all())->toBe([Role::Student]);
    expect($student->universityId)->not->toBeEmpty();

    // Grades across two completed Semesters, so the Card has both numbers to show — a demo
    // where only the CGPA resolves would hide half of what the Card is for.
    expect(app(GradingRecords::class)->cgpaFor($student->studentId))->not->toBeNull();

    Sanctum::actingAs($user);
    $card = $this->getJson('/api/v1/grading/cards/academic-record')->assertOk();

    expect($card->json('data.cgpa'))->not->toBeNull();
    expect($card->json('data.latest_semester_gpa'))->not->toBeNull();
    expect($card->json('data.latest_semester.name_en'))->not->toBeNull();
    expect($card->json('data.earned_credit_hours'))->toBeGreaterThan(0);
});

it('gives r03@ advisees with a request genuinely pending their approval', function () {
    $this->seed(DatabaseSeeder::class);

    $advisorId = (int) rasUser('r03@ejust.test')->getKey();
    $advisees = Student::query()->where('advisor_id', $advisorId)->pluck('id');

    expect($advisees->count())->toBeGreaterThanOrEqual(2);
    expect(
        RegistrationRequest::query()
            ->whereIn('student_id', $advisees)
            ->where('status', RegistrationStatus::Pending)
            ->count(),
    )->toBeGreaterThanOrEqual(1);
});

it('gives multi@ both halves of its two roles, so the composition rule is demonstrable', function () {
    $this->seed(DatabaseSeeder::class);

    $multi = rasUser('multi@ejust.test');
    expect($multi->roles()->all())->toBe([Role::Advisor, Role::Instructor]);

    // The Advisor half: advisees, one of them waiting on a decision.
    $advisees = Student::query()->where('advisor_id', $multi->getKey())->pluck('id');
    expect($advisees->count())->toBeGreaterThanOrEqual(2);
    expect(
        RegistrationRequest::query()
            ->whereIn('student_id', $advisees)
            ->where('status', RegistrationStatus::Pending)
            ->count(),
    )->toBeGreaterThanOrEqual(1);

    // The Instructor half: Offerings of its own, with a roster and an unfinalized Sheet — the
    // union of two roles' work on one account, which is the only thing that demonstrates a
    // Dashboard composed from access rather than selected by Role (ADR-0024).
    $offerings = CourseOffering::query()->where('instructor_id', $multi->getKey())->get();
    expect($offerings->count())->toBeGreaterThanOrEqual(2);
    expect(Enrollment::query()->whereIn('course_offering_id', $offerings->modelKeys())->count())
        ->toBeGreaterThan(0);

    $unfinalized = GradeSheet::query()
        ->whereIn('course_offering_id', $offerings->modelKeys())
        ->whereNot('status', GradeSheetStatus::Finalized)
        ->count();
    expect($unfinalized)->toBeGreaterThanOrEqual(1);
});

it('leaves the phase-3 and phase-4 worlds it builds on undisturbed', function () {
    $this->seed(DatabaseSeeder::class);

    // No new Semester: the five-term chronology the Incomplete lifecycle depends on is exact.
    expect(Semester::query()->count())->toBe(5);

    // The late-flagging demo keeps its single subject — an Offering in a past-deadline Semester
    // whose only round is still awaiting review. A new Fall 2025 Draft Sheet must not join it.
    $pastDeadline = Semester::query()
        ->where('grade_submission_deadline_at', '<', now())
        ->pluck('id');
    expect(
        GradeSheet::query()
            ->whereIn('course_offering_id', CourseOffering::query()->whereIn('semester_id', $pastDeadline)->select('id'))
            ->where('status', GradeSheetStatus::Submitted)
            ->count(),
    )->toBe(1);

    // Exactly one Returned round, still the one carrying the review note.
    expect(GradeSheet::query()->where('status', GradeSheetStatus::Returned)->count())->toBe(1);

    // The Phase-4 headline smoke's Sheet is still Draft and still blank.
    $smokeOffering = CourseOffering::query()
        ->where('semester_id', Semester::query()->where('term', Term::Spring)->where('year', 2025)->value('id'))
        ->whereIn('course_id', Course::query()->where('code', 'CSE531')->select('id'))
        ->firstOrFail();
    $smokeSheet = GradeSheet::query()->where('course_offering_id', $smokeOffering->getKey())->firstOrFail();
    expect($smokeSheet->status)->toBe(GradeSheetStatus::Draft);
    expect($smokeSheet->grades()->count())->toBe(0);
});

it('seeds the role accounts idempotently', function () {
    $this->seed(DatabaseSeeder::class);

    $before = [
        'students' => Student::query()->count(),
        'offerings' => CourseOffering::query()->count(),
        'requests' => RegistrationRequest::query()->count(),
        'sheets' => GradeSheet::query()->count(),
        'grades' => GradeSheet::query()->withCount('grades')->get()->sum('grades_count'),
        'universityIds' => Student::query()->orderBy('id')->pluck('university_id')->all(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect(Student::query()->count())->toBe($before['students']);
    expect(CourseOffering::query()->count())->toBe($before['offerings']);
    expect(RegistrationRequest::query()->count())->toBe($before['requests']);
    expect(GradeSheet::query()->count())->toBe($before['sheets']);
    expect(GradeSheet::query()->withCount('grades')->get()->sum('grades_count'))->toBe($before['grades']);

    // The subtle one: minting r02@ twice would burn a second University ID from the generator
    // even if the Student row were reused, so the whole allocated sequence is compared.
    expect(Student::query()->orderBy('id')->pluck('university_id')->all())->toBe($before['universityIds']);
});
