<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('combines live Advisor and Dean scope when reading current Standing', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::Advisor);
    $reviewer->assignRole(Role::Dean);
    $program = Program::factory()->create();
    $assignment = FacultyDeanAssignment::factory()->create([
        'user_id' => $reviewer->id,
        'faculty_id' => $program->department->faculty_id,
    ]);
    $facultyStudent = Student::factory()->create(['program_id' => $program->id]);
    $advisee = Student::factory()->create(['advisor_id' => $reviewer->id]);
    $outside = Student::factory()->create();
    Sanctum::actingAs($reviewer);

    $this->getJson("/api/v1/academic-standing/students/{$facultyStudent->id}")->assertOk();
    $this->getJson("/api/v1/academic-standing/students/{$advisee->id}")->assertOk();
    $this->getJson("/api/v1/academic-standing/students/{$outside->id}")->assertForbidden();

    $assignment->delete();
    $this->getJson("/api/v1/academic-standing/students/{$facultyStudent->id}")->assertForbidden();
    $this->getJson("/api/v1/academic-standing/students/{$advisee->id}")->assertOk();
    $advisee->update(['advisor_id' => User::factory()->create()->id]);
    $this->getJson("/api/v1/academic-standing/students/{$advisee->id}")->assertForbidden();
});
