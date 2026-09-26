<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Internal\Support\StandingReconciler;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Internal\Models\Notification;

uses(RefreshDatabase::class);

final class MutableStandingGrading implements GradingRecords
{
    /** @param list<EffectiveAttemptSummary> $attempts */
    public function __construct(public ?float $cgpa = null, public array $attempts = []) {}

    public function effectiveAttemptsForStudent(int $studentId): array
    {
        return $this->attempts;
    }

    public function cgpaFor(int $studentId): ?float
    {
        return $this->cgpa;
    }

    public function semesterGpaFor(int $studentId, int $semesterId): ?float
    {
        return null;
    }

    public function gradesFor(array $enrollmentIds): array
    {
        return [];
    }
}

it('keeps reads side-effect free and records each real Standing transition once', function () {
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create(['advisor_id' => $advisor->id]);
    $student->user->assignRole(Role::Student);
    $grading = new MutableStandingGrading;
    app()->instance(GradingRecords::class, $grading);
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/academic-standing/me')
        ->assertOk()
        ->assertJsonPath('data.standing', 'NotYetAssessed')
        ->assertJsonPath('data.observations', []);
    expect(StandingObservation::query()->count())->toBe(0);

    $reconciler = app(StandingReconciler::class);
    $reconciler->reconcile($student->id, 'command');
    $reconciler->reconcile($student->id, 'command');
    expect(StandingObservation::query()->count())->toBe(1)
        ->and(Notification::query()->count())->toBe(0);

    $grading->cgpa = 2.99;
    $grading->attempts = [new EffectiveAttemptSummary(11, 1, 10, 'Core', 3, false, 'D')];
    $reconciler->reconcile($student->id, 'grade_sheet', 7);
    $reconciler->reconcile($student->id, 'grade_sheet', 7);
    expect(StandingObservation::query()->count())->toBe(2)
        ->and(Notification::query()->where('type', 'academic_standing.changed')->count())->toBe(2);

    $grading->cgpa = 3.5;
    $grading->attempts = [new EffectiveAttemptSummary(12, 2, 10, 'Core', 3, true, 'B+')];
    $reconciler->reconcile($student->id, 'grade_sheet', 8);
    expect(StandingObservation::query()->count())->toBe(3)
        ->and(Notification::query()->where('type', 'academic_standing.changed')->count())->toBe(4);

    $this->getJson('/api/v1/academic-standing/me')
        ->assertOk()
        ->assertJsonPath('data.standing', 'GoodStanding')
        ->assertJsonCount(3, 'data.observations');
});

it('applies the probation registration ceiling through the Public result', function () {
    $student = Student::factory()->create();
    $student->user->assignRole(Role::Student);
    app()->instance(GradingRecords::class, new MutableStandingGrading(
        2.5,
        [new EffectiveAttemptSummary(11, 1, 10, 'Core', 3, true, 'C+')],
    ));
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/academic-standing/me')
        ->assertOk()
        ->assertJsonPath('data.standing', 'AcademicProbation')
        ->assertJsonPath('data.registration_ceiling_ch', 6);
});
