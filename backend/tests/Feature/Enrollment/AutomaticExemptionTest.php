<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Public\AdmissionAcceptanceEvidence;
use Modules\Admissions\Public\Events\AdmissionAccepted;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Support\AutomaticExemptionApplicator;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/**
 * @param  list<array{course_id: int, course_code: string, role: PreparatoryRequirementRole}>  $entries
 */
function studentBoundToAutomaticExemptionVersion(Program $program, array $entries): Student
{
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => [
            'curriculum' => array_map(static fn (array $entry): array => [
                'course_id' => $entry['course_id'],
                'course_code' => $entry['course_code'],
                'course_total_ch' => 1,
                'category' => CourseCategory::Preparatory->value,
                'preparatory_requirement_role' => $entry['role']->value,
                'ordinal' => 0,
            ], $entries),
        ],
        'changed_at' => now(),
    ]);

    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
    ]);
    $student->user->assignRole(Role::Student);

    return $student;
}

function publishAcceptanceEvidence(Student $student, array $overrides = []): void
{
    $admittedProgram = Program::query()->findOrFail($student->program_id);
    $evidence = array_merge([
        'studentId' => (int) $student->id,
        'userId' => (int) $student->user_id,
        'applicationId' => 9001,
        'admittedProgramId' => (int) $student->program_id,
        'admittedProgramDegreeLevel' => $admittedProgram->degree_level->value,
        'admittedDepartmentId' => (int) $admittedProgram->department_id,
        'previousDegree' => 'BSc',
        'isEjustGraduate' => false,
        'previousEjustProgramId' => null,
        'previousEjustProgramDegreeLevel' => null,
        'previousEjustDepartmentId' => null,
        'englishTest' => 'IELTS',
        'englishScore' => 6.5,
    ], $overrides);

    if ($evidence['previousEjustProgramId'] !== null && ! array_key_exists('previousEjustProgramDegreeLevel', $overrides)) {
        $previous = Program::query()->findOrFail($evidence['previousEjustProgramId']);
        $evidence['previousEjustProgramDegreeLevel'] = $previous->degree_level->value;
        $evidence['previousEjustDepartmentId'] = (int) $previous->department_id;
    }

    Event::dispatch(new AdmissionAccepted(
        evidence: new AdmissionAcceptanceEvidence(...$evidence),
        universityId: $student->university_id,
    ));
}

it('applies and exposes an automatic English Exemption from accepted evidence', function () {
    $department = Department::factory()->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);
    $student = studentBoundToAutomaticExemptionVersion($program, [
        ['course_id' => 501, 'course_code' => 'OLD-ENG', 'role' => PreparatoryRequirementRole::EnglishLanguage],
        ['course_id' => 502, 'course_code' => 'OLD-ARA', 'role' => PreparatoryRequirementRole::ArabicLanguage],
    ]);

    publishAcceptanceEvidence($student);

    Sanctum::actingAs($student->user);
    test()->getJson('/api/v1/credit-decisions/exemptions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $student->id)
        ->assertJsonPath('data.0.curriculum_version_id', $student->curriculum_version_id)
        ->assertJsonPath('data.0.target_course_id', 501)
        ->assertJsonPath('data.0.target_course_code', 'OLD-ENG')
        ->assertJsonPath('data.0.preparatory_requirement_role', 'EnglishLanguage')
        ->assertJsonPath('data.0.credit_hours', 1)
        ->assertJsonPath('data.0.source', 'automatic')
        ->assertJsonPath('data.0.reason_codes.0', 'english_proficiency')
        ->assertJsonPath('data.0.admission_application_id', 9001);

    $this->assertDatabaseCount('exemption_decisions', 1);
    $this->assertDatabaseCount('enrollments', 0);
    $this->assertDatabaseHas('audit_log', [
        'action' => 'exemption.automatically_applied',
        'subject_type' => 'exemption_decision',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $student->user_id,
        'type' => 'exemption.automatically_applied',
    ]);
});

it('deduplicates overlapping automatic rules, history, and notifications', function () {
    $department = Department::factory()->create();
    $admittedProgram = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::PhD,
    ]);
    $previousProgram = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);
    $entries = collect(PreparatoryRequirementRole::cases())->values()->map(
        static fn (PreparatoryRequirementRole $role, int $index): array => [
            'course_id' => 600 + $index,
            'course_code' => 'PRE'.($index + 1),
            'role' => $role,
        ],
    )->all();
    $student = studentBoundToAutomaticExemptionVersion($admittedProgram, $entries);

    $evidence = [
        'previousDegree' => 'MSc',
        'isEjustGraduate' => true,
        'previousEjustProgramId' => $previousProgram->id,
        'englishTest' => 'IELTS',
        'englishScore' => 7.0,
    ];
    publishAcceptanceEvidence($student, $evidence);
    publishAcceptanceEvidence($student, $evidence);

    $this->assertDatabaseCount('automatic_exemption_evidence', 1);
    expect(DB::table('automatic_exemption_evidence')->value('reconciled_at'))->not->toBeNull();
    $this->assertDatabaseCount('exemption_decisions', 6);
    expect(DB::table('notifications')->where('type', 'exemption.automatically_applied')->count())->toBe(6);
    expect(DB::table('notifications')->where('type', 'application.accepted')->count())->toBe(1);
    expect(DB::table('audit_log')->where('action', 'exemption.automatically_applied')->count())->toBe(6);

    $english = DB::table('exemption_decisions')
        ->where('preparatory_requirement_role', PreparatoryRequirementRole::EnglishLanguage->value)
        ->first();
    expect(json_decode($english->reason_codes, true))->toBe([
        'english_proficiency',
        'same_department_ejust_msc',
    ]);
});

it('maps E-JUST BSc evidence to the three semantic Preparatory roles only', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $entries = collect(PreparatoryRequirementRole::cases())->values()->map(
        static fn (PreparatoryRequirementRole $role, int $index): array => [
            'course_id' => 800 + $index,
            'course_code' => 'ARBITRARY-'.$index,
            'role' => $role,
        ],
    )->all();
    $student = studentBoundToAutomaticExemptionVersion($program, $entries);

    publishAcceptanceEvidence($student, [
        'previousDegree' => 'BSc',
        'isEjustGraduate' => true,
        'englishTest' => 'IELTS',
        'englishScore' => 6.0,
    ]);

    expect(DB::table('exemption_decisions')->orderBy('target_course_id')->pluck('preparatory_requirement_role')->all())
        ->toBe([
            PreparatoryRequirementRole::JapaneseLanguage->value,
            PreparatoryRequirementRole::JapaneseCulture->value,
            PreparatoryRequirementRole::KeySkills->value,
        ]);
});

it('reconciles one Student from the captured immutable evidence and is safe to rerun', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = studentBoundToAutomaticExemptionVersion($program, [[
        'course_id' => 701,
        'course_code' => 'PRE701',
        'role' => PreparatoryRequirementRole::EnglishLanguage,
    ]]);
    $evidence = new AdmissionAcceptanceEvidence(
        studentId: (int) $student->id,
        userId: (int) $student->user_id,
        applicationId: 9901,
        admittedProgramId: (int) $student->program_id,
        admittedProgramDegreeLevel: DegreeLevel::MSc->value,
        admittedDepartmentId: (int) Program::query()->findOrFail($student->program_id)->department_id,
        previousDegree: 'BSc',
        isEjustGraduate: false,
        previousEjustProgramId: null,
        previousEjustProgramDegreeLevel: null,
        previousEjustDepartmentId: null,
        englishTest: 'TOEFL iBT',
        englishScore: 79,
    );

    // This is the durable first half of post-commit handling; evaluation was missed.
    app(AutomaticExemptionApplicator::class)->capture($evidence);
    $this->assertDatabaseCount('exemption_decisions', 0);

    $this->artisan('enrollment:reconcile-automatic-exemptions', ['student' => $student->id])
        ->expectsOutputToContain('Created 1 automatic Exemption decision')
        ->assertSuccessful();
    $this->artisan('enrollment:reconcile-automatic-exemptions', ['student' => $student->id])
        ->expectsOutputToContain('Created 0 automatic Exemption decisions')
        ->assertSuccessful();

    $this->assertDatabaseCount('exemption_decisions', 1);
    $this->assertDatabaseCount('notifications', 1);
});

it('reconciles from the Student acceptance anchor when event handling was wholly missed', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = studentBoundToAutomaticExemptionVersion($program, [[
        'course_id' => 711,
        'course_code' => 'PRE711',
        'role' => PreparatoryRequirementRole::EnglishLanguage,
    ]]);
    $student->update(['admission_evidence' => [
        'user_id' => (int) $student->user_id,
        'application_id' => 9911,
        'admitted_program_id' => (int) $program->id,
        'admitted_program_degree_level' => DegreeLevel::MSc->value,
        'admitted_department_id' => (int) $program->department_id,
        'previous_degree' => 'BSc',
        'is_ejust_graduate' => false,
        'previous_ejust_program_id' => null,
        'previous_ejust_program_degree_level' => null,
        'previous_ejust_department_id' => null,
        'english_test' => 'IELTS',
        'english_score' => 6.5,
    ]]);

    $this->assertDatabaseCount('automatic_exemption_evidence', 0);
    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/credit-decisions/exemptions', ['Accept-Language' => 'en'])
        ->assertConflict()
        ->assertJsonPath('message', 'Your admission exemptions are still being reconciled. Retry before composing registration.');
    $this->getJson('/api/v1/registration/eligible-courses', ['Accept-Language' => 'en'])->assertConflict();
    $this->postJson('/api/v1/registration/requests', ['lines' => []], ['Accept-Language' => 'ar'])
        ->assertConflict()
        ->assertJsonPath('message', 'لا تزال إعفاءات القبول قيد التسوية. أعد المحاولة قبل إعداد التسجيل.');

    $this->artisan('enrollment:reconcile-automatic-exemptions', ['student' => $student->id])
        ->expectsOutputToContain('Created 1 automatic Exemption decision')
        ->assertSuccessful();

    $this->assertDatabaseCount('automatic_exemption_evidence', 1);
    $this->assertDatabaseHas('exemption_decisions', ['target_course_id' => 711]);
    $this->getJson('/api/v1/credit-decisions/exemptions')->assertOk();
});

it('uses acceptance-time Program semantics when delayed reconciliation runs', function () {
    $department = Department::factory()->create();
    $admittedProgram = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::PhD,
    ]);
    $previousProgram = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);
    $entries = collect(PreparatoryRequirementRole::cases())->values()->map(
        static fn (PreparatoryRequirementRole $role, int $index): array => [
            'course_id' => 900 + $index,
            'course_code' => 'IMM'.($index + 1),
            'role' => $role,
        ],
    )->all();
    $student = studentBoundToAutomaticExemptionVersion($admittedProgram, $entries);
    $evidence = new AdmissionAcceptanceEvidence(
        studentId: (int) $student->id,
        userId: (int) $student->user_id,
        applicationId: 9921,
        admittedProgramId: (int) $admittedProgram->id,
        admittedProgramDegreeLevel: DegreeLevel::PhD->value,
        admittedDepartmentId: (int) $department->id,
        previousDegree: 'MSc',
        isEjustGraduate: true,
        previousEjustProgramId: (int) $previousProgram->id,
        previousEjustProgramDegreeLevel: DegreeLevel::MSc->value,
        previousEjustDepartmentId: (int) $department->id,
        englishTest: 'IELTS',
        englishScore: 6.0,
    );
    app(AutomaticExemptionApplicator::class)->capture($evidence);

    $previousProgram->update(['department_id' => Department::factory()->create()->id]);
    app(AutomaticExemptionApplicator::class)->applyForStudent((int) $student->id);

    $this->assertDatabaseCount('exemption_decisions', 6);
});
