<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function recordsStudent(): Student
{
    $program = Program::factory()->create();
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program, 'subject_id' => $program->id, 'version_no' => 1,
        'snapshot' => ['prep_required_ch' => 6, 'core_required_ch' => 6, 'elective_required_ch' => 9, 'curriculum' => []],
        'changed_at' => now(),
    ]);
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create([
        'program_id' => $program->id, 'curriculum_version_id' => $version->id,
        'advisor_id' => $advisor->id, 'identification_type' => 'passport', 'national_id' => 'AB123456',
    ]);
    $student->user->assignRole(Role::Student);

    return $student;
}

it('composes admission identity, all approved Enrollments and only finalized Grades through owners', function () {
    $student = recordsStudent();
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'credit_hours' => 2]);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $enrollment->course_offering_id]);
    Grade::factory()->withMark(95)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonPath('data.identity.identification_number', 'AB123456')
        ->assertJsonPath('data.cgpa', null)
        ->assertJsonPath('data.semesters.0.courses.0.credit_hours', 2)
        ->assertJsonPath('data.semesters.0.courses.0.result', 'InProgress')
        ->assertJsonPath('data.credit_hours.total.baseline_required_ch', 21)
        ->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.financial');
    $sheet->update(['status' => GradeSheetStatus::Finalized, 'reviewed_at' => now()]);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonPath('data.semesters.0.courses.0.result', 'A+')
        ->assertJsonPath('data.cgpa', 4);
});

it('keeps transcript read scope live and preserves mixed-role unions', function () {
    $student = recordsStudent();
    Sanctum::actingAs($student->advisor);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk();
    $former = $student->advisor;
    $replacement = User::factory()->create();
    $replacement->assignRole(Role::Advisor);
    $student->update(['advisor_id' => $replacement->id, 'lifecycle_status' => 'Terminated']);
    Sanctum::actingAs($former);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertForbidden();
    $former->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($former->fresh());
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk();
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()->assertJsonPath('data.lifecycle_status', 'Terminated');
});

it('refuses other Students and Roles including deferred PDF holders', function (Role $role) {
    $student = recordsStudent();
    actingAsRole($role);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertForbidden();
})->with([Role::Student, Role::Instructor, Role::Dean, Role::DepartmentHead, Role::Council]);

it('prints the owner Course Status outcomes without exposing marks or inventing completion', function (string $status, string $result) {
    $student = recordsStudent();
    Enrollment::factory()->create(['student_id' => $student->id, 'course_status' => $status]);
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonPath('data.semesters.0.courses.0.result', $result)
        ->assertJsonPath('data.cgpa', null);
})->with([['Audit', 'AU'], ['Withdrawn', 'W'], ['Incomplete', 'I'], ['FailWithdrawal', 'FW']]);

it('filters and paginates only readable scoped Student fields and audits matching exports', function () {
    $student = recordsStudent();
    $student->update(['name_en' => 'Unique Transcript Student', 'name_ar' => 'طالب كشف فريد']);
    recordsStudent();
    Sanctum::actingAs($student->advisor);
    $this->getJson('/api/v1/records/students?q=Unique&per_page=1')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $student->id)->assertJsonPath('meta.total', 1)
        ->assertJsonMissingPath('data.0.identification_number');
    $this->getJson('/api/v1/records/students?national_id=AB123456')->assertUnprocessable();
    $this->get('/api/v1/records/students/export?q=Unique')->assertOk()->assertDownload('transcript-students.xlsx');
    $this->assertDatabaseHas('audit_log', ['action' => 'records.students_exported']);
});

it('shows effective qualification-based decisions separately and never leaks their evidence', function () {
    $student = recordsStudent();
    $decision = ExemptionDecision::create([
        'student_id' => $student->id, 'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => 98765, 'target_course_code' => 'ENGLISH', 'preparatory_requirement_role' => 'EnglishLanguage',
        'credit_hours' => 1, 'source' => 'automatic', 'reason_codes' => ['IELTS'],
        'source_evidence' => ['private_document' => 'secret'], 'approved_at' => now(),
    ]);
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonPath('data.credit_decisions.0.type', 'Exemption')
        ->assertJsonPath('data.credit_decisions.0.target_course_code', 'ENGLISH')
        ->assertJsonPath('data.credit_decisions.0.reason_codes.0', 'IELTS')
        ->assertJsonCount(0, 'data.semesters')->assertJsonMissingPath('data.credit_decisions.0.source_evidence')
        ->assertDontSee('secret');
    CreditDecisionRevocation::create([
        'decision_type' => 'exemption', 'decision_id' => $decision->id, 'student_id' => $student->id,
        'target_course_id' => 98765, 'reason' => 'Corrected qualification', 'created_at' => now(),
    ]);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()->assertJsonCount(0, 'data.credit_decisions');
});

it('exposes only approved Transfer and Waiver source details rather than financial or private evidence', function () {
    $student = recordsStudent();
    $common = ['student_id' => $student->id, 'curriculum_version_id' => $student->curriculum_version_id];
    $transfer = array_merge($common, [
        'target_course_id' => 10001, 'target_course_code' => 'CORE-T', 'target_category' => 'ProgramCore',
        'target_credit_hours' => 3, 'external_institution' => 'Source University', 'external_course_code' => 'EXT1',
        'external_course_name' => 'External Algorithms', 'source_credit_hours' => '3.00', 'external_grade' => 'Distinction',
        'document_ids' => ['private-transfer'], 'normalized_grade' => 'B', 'verified_by_user_id' => $student->advisor_id,
        'verified_at' => now(),
    ]);
    $request = TransferRequest::create(array_merge($transfer, ['status' => 'Approved']));
    TransferDecision::create(array_merge($transfer, [
        'transfer_request_id' => $request->id, 'approved_by_user_id' => $student->advisor_id, 'approved_at' => now(),
    ]));
    $waiver = array_merge($common, ['target_course_id' => 10002, 'target_course_code' => 'CORE-W', 'credit_hours' => 3,
        'prior_course_id' => 999, 'prior_course_code' => 'OLD401', 'prior_course_level' => 400,
        'prior_result_source' => 'external_evidence', 'prior_letter_grade' => 'B+',
    ]);
    $request = WaiverRequest::create(array_merge($waiver, ['status' => 'Approved', 'evidence' => ['private-waiver']]));
    WaiverDecision::create(array_merge($waiver, [
        'waiver_request_id' => $request->id, 'coverage_percentage' => 80, 'verified_by' => $student->advisor_id,
        'verified_at' => now(), 'approved_by' => $student->advisor_id, 'approved_at' => now(),
    ]));
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonPath('data.credit_decisions.0.type', 'Transfer')
        ->assertJsonPath('data.credit_decisions.0.source_institution', 'Source University')
        ->assertJsonPath('data.credit_decisions.0.original_grade', 'Distinction')
        ->assertJsonPath('data.credit_decisions.0.normalized_grade', 'B')
        ->assertJsonPath('data.credit_decisions.1.type', 'Waiver')
        ->assertJsonPath('data.credit_decisions.1.prior_course_code', 'OLD401')
        ->assertJsonPath('data.credit_decisions.1.prior_grade', 'B+')
        ->assertDontSee('private-transfer')->assertDontSee('private-waiver');
});

it('retains every Attempt and owner-derived supersession and retroactive Semester GPAs', function () {
    $student = recordsStudent();
    $course = Course::factory()->create();
    $firstSemester = Semester::factory()->create(['starts_at' => '2025-02-01']);
    $laterSemester = Semester::factory()->create(['starts_at' => '2026-02-01']);
    $firstOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $firstSemester->id]);
    $laterOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $laterSemester->id]);
    $first = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $firstOffering->id, 'credit_hours' => 2]);
    $retake = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $laterOffering->id, 'credit_hours' => 2, 'course_status' => 'Retake']);
    foreach ([[$first, 60], [$retake, 80]] as [$enrollment, $mark]) {
        $sheet = GradeSheet::factory()->create(['course_offering_id' => $enrollment->course_offering_id, 'status' => GradeSheetStatus::Finalized, 'reviewed_at' => now()]);
        Grade::factory()->withMark($mark)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    }
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()
        ->assertJsonCount(2, 'data.semesters')->assertJsonPath('data.semesters.0.courses.0.result', 'D')
        ->assertJsonPath('data.semesters.0.courses.0.contributes_to_cgpa', false)->assertJsonPath('data.semesters.0.gpa', null)
        ->assertJsonPath('data.semesters.1.courses.0.contributes_to_cgpa', true)->assertJsonPath('data.cgpa', 3);
    // A finalized Correction is a new owner round, not an edit to the old Grade.
    $correction = GradeSheet::factory()->create(['course_offering_id' => $laterOffering->id, 'kind' => 'Correction', 'status' => GradeSheetStatus::Finalized, 'reviewed_at' => now()->addMinute()]);
    Grade::factory()->withMark(95)->create(['grade_sheet_id' => $correction->id, 'enrollment_id' => $retake->id]);
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()->assertJsonPath('data.semesters.1.courses.0.result', 'A+')
        ->assertJsonPath('data.cgpa', 4)->assertJsonPath('data.semesters.0.courses.0.result', 'D');
});

it('preserves owner target accounting when an exempted Course also earns credit', function () {
    $student = recordsStudent();
    $course = Course::factory()->create(['total_ch' => 1, 'lecture_ch' => 1, 'tutorial_ch' => 0, 'lab_ch' => 0]);
    $version = CatalogVersion::query()->findOrFail($student->curriculum_version_id);
    $version->update(['snapshot' => ['prep_required_ch' => 6, 'core_required_ch' => 6, 'elective_required_ch' => 9, 'curriculum' => [
        ['course_id' => $course->id, 'course_code' => $course->code, 'course_total_ch' => 1, 'category' => 'Preparatory', 'preparatory_requirement_role' => 'EnglishLanguage', 'ordinal' => 0],
    ]]]);
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id, 'credit_hours' => 1, 'classification' => 'Preparatory', 'earned' => true]);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $offering->id, 'status' => GradeSheetStatus::Finalized, 'reviewed_at' => now()]);
    Grade::factory()->withMark(95)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    ExemptionDecision::create(['student_id' => $student->id, 'curriculum_version_id' => $version->id, 'target_course_id' => $course->id, 'target_course_code' => $course->code,
        'preparatory_requirement_role' => 'EnglishLanguage', 'credit_hours' => 1, 'source' => 'automatic', 'reason_codes' => ['IELTS'], 'source_evidence' => [], 'approved_at' => now()]);
    Sanctum::actingAs($student->user);
    $owner = $this->getJson('/api/v1/academic-standing/me/credit-hours')->assertOk()->json('data');
    $this->getJson("/api/v1/records/students/{$student->id}/transcript")->assertOk()->assertJsonPath('data.credit_hours', $owner)
        ->assertJsonPath('data.credit_hours.categories.0.earned_ch', 1)->assertJsonPath('data.credit_hours.categories.0.exempted_ch', 1)
        ->assertJsonPath('data.credit_hours.categories.0.remaining_ch', 5);
});
