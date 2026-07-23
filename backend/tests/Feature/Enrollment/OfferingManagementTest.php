<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Course Offering management — "what actually runs this semester" (ADR-0017, ticket
| 03). One Catalog Course taught in one Calendar Semester, carrying an Instructor, an
| optional capacity, and one or more weekly meetings. All CRUD is gated on
| calendar.manage (Student Affairs / Dean; SysAdmin inherits). One Course runs at most
| once per Semester, an Offering's meetings may not overlap each other, and an Offering
| is deletable only while nothing references it. Written against the /api/v1 HTTP seam.
*/

/** A faculty (R04) User who may be assigned to teach. */
function instructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/**
 * A valid create payload. `meetings` defaults to a single Monday 09:00–11:00 slot.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function offeringPayload(int $courseId, int $instructorId, array $overrides = []): array
{
    return array_merge([
        'course_id' => $courseId,
        'instructor_id' => $instructorId,
        'capacity' => 30,
        'meetings' => [
            ['weekday' => Weekday::Monday->value, 'starts_at' => '09:00', 'ends_at' => '11:00'],
        ],
    ], $overrides);
}

it('lists a semesters offerings with resolved course, instructor and meetings', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create(['code' => 'DEP501', 'name_en' => 'Advanced Algorithms']);
    $teacher = instructor();
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $teacher->id,
        'capacity' => 25,
    ]);
    OfferingMeeting::factory()->slot(Weekday::Tuesday, '13:00', '15:00')->create([
        'course_offering_id' => $offering->id,
    ]);
    // A second semester's offering must not leak into this list.
    CourseOffering::factory()->create();

    actingAsRole(Role::StudentAffairs);

    $this->getJson("/api/v1/semesters/{$semester->id}/offerings")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $offering->id)
        ->assertJsonPath('data.0.capacity', 25)
        ->assertJsonPath('data.0.course.code', 'DEP501')
        ->assertJsonPath('data.0.instructor.id', $teacher->id)
        ->assertJsonPath('data.0.meetings.0.weekday', Weekday::Tuesday->value)
        ->assertJsonPath('data.0.meetings.0.starts_at', '13:00')
        ->assertJsonPath('data.0.meetings.0.ends_at', '15:00');
});

it('lets a calendar manager create an offering', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create(['code' => 'CSE511']);
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id))
        ->assertCreated()
        ->assertJsonPath('data.course.code', 'CSE511')
        ->assertJsonPath('data.instructor.id', $teacher->id)
        ->assertJsonPath('data.capacity', 30)
        ->assertJsonPath('data.meetings.0.starts_at', '09:00');

    $this->assertDatabaseHas('course_offerings', [
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $teacher->id,
        'capacity' => 30,
    ]);
    $offering = CourseOffering::query()->firstOrFail();
    $this->assertDatabaseHas('offering_meetings', [
        'course_offering_id' => $offering->id,
        'weekday' => Weekday::Monday->value,
    ]);
});

it('lets a Dean create an offering too (also holds calendar.manage)', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::Dean);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id))
        ->assertCreated();
});

it('creates an uncapped offering when capacity is omitted', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson(
        "/api/v1/semesters/{$semester->id}/offerings",
        offeringPayload($course->id, $teacher->id, ['capacity' => null]),
    )
        ->assertCreated()
        ->assertJsonPath('data.capacity', null);
});

it('rejects a second offering of the same course in the same semester', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors('course_id');
});

it('allows the same course to be offered in a different semester', function () {
    $fall = Semester::factory()->create();
    $spring = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    CourseOffering::factory()->create(['semester_id' => $fall->id, 'course_id' => $course->id]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$spring->id}/offerings", offeringPayload($course->id, $teacher->id))
        ->assertCreated();
});

it('rejects meetings that overlap each other', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id, [
        'meetings' => [
            ['weekday' => Weekday::Monday->value, 'starts_at' => '09:00', 'ends_at' => '11:00'],
            ['weekday' => Weekday::Monday->value, 'starts_at' => '10:00', 'ends_at' => '12:00'],
        ],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('meetings');
});

it('allows two meetings on the same day that only touch at an endpoint', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id, [
        'meetings' => [
            ['weekday' => Weekday::Monday->value, 'starts_at' => '09:00', 'ends_at' => '11:00'],
            ['weekday' => Weekday::Monday->value, 'starts_at' => '11:00', 'ends_at' => '13:00'],
        ],
    ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.meetings');
});

it('rejects a meeting that ends before it starts', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id, [
        'meetings' => [
            ['weekday' => Weekday::Monday->value, 'starts_at' => '11:00', 'ends_at' => '09:00'],
        ],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('meetings');
});

it('rejects an instructor who is not a faculty member', function () {
    $semester = Semester::factory()->create();
    $course = Course::factory()->create();
    $notFaculty = User::factory()->create(); // holds no Instructor role
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $notFaculty->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors('instructor_id');
});

it('rejects a course that does not exist in the catalog', function () {
    $semester = Semester::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload(999_999, $teacher->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors('course_id');
});

it('requires the course, instructor and at least one meeting', function () {
    $semester = Semester::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_id', 'instructor_id', 'meetings']);
});

it('404s when creating an offering for a semester that does not exist', function () {
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters/999999/offerings', offeringPayload($course->id, $teacher->id))
        ->assertNotFound();
});

it('shows an offering with its resolved references', function () {
    $offering = CourseOffering::factory()->create();
    OfferingMeeting::factory()->create(['course_offering_id' => $offering->id]);
    actingAsRole(Role::StudentAffairs);

    $this->getJson("/api/v1/offerings/{$offering->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $offering->id)
        ->assertJsonStructure(['data' => ['id', 'semester_id', 'course_id', 'instructor_id', 'capacity', 'course', 'instructor', 'meetings']]);
});

it('updates an offering instructor, capacity and meetings, replacing the old meetings', function () {
    $offering = CourseOffering::factory()->create(['capacity' => 30]);
    $original = OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create([
        'course_offering_id' => $offering->id,
    ]);
    $newTeacher = instructor();
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/offerings/{$offering->id}", [
        'instructor_id' => $newTeacher->id,
        'capacity' => 15,
        'meetings' => [
            ['weekday' => Weekday::Wednesday->value, 'starts_at' => '14:00', 'ends_at' => '16:00'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.instructor.id', $newTeacher->id)
        ->assertJsonPath('data.capacity', 15)
        ->assertJsonCount(1, 'data.meetings')
        ->assertJsonPath('data.meetings.0.weekday', Weekday::Wednesday->value);

    $this->assertDatabaseMissing('offering_meetings', ['id' => $original->id]);
    $this->assertDatabaseHas('course_offerings', ['id' => $offering->id, 'instructor_id' => $newTeacher->id, 'capacity' => 15]);
});

it('deletes an unreferenced offering and cascades its meetings', function () {
    $offering = CourseOffering::factory()->create();
    $meeting = OfferingMeeting::factory()->create(['course_offering_id' => $offering->id]);
    actingAsRole(Role::StudentAffairs);

    $this->deleteJson("/api/v1/offerings/{$offering->id}")->assertNoContent();

    $this->assertDatabaseMissing('course_offerings', ['id' => $offering->id]);
    $this->assertDatabaseMissing('offering_meetings', ['id' => $meeting->id]);
});

it('refuses to delete an offering that is referenced by a session or enrollment', function () {
    $offering = CourseOffering::factory()->create();
    actingAsRole(Role::StudentAffairs);

    // Stand up a minimal referencing table (real class_sessions arrives in ticket 08)
    // to exercise the delete guard's forward-compatible reference check.
    Schema::create('class_sessions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('course_offering_id');
    });
    DB::table('class_sessions')->insert(['course_offering_id' => $offering->id]);

    $this->deleteJson("/api/v1/offerings/{$offering->id}")->assertStatus(409);

    $this->assertDatabaseHas('course_offerings', ['id' => $offering->id]);

    Schema::drop('class_sessions');
});

it('lists assignable instructors for the picker', function () {
    $a = instructor();
    $b = instructor();
    User::factory()->create(); // a non-faculty user must not appear
    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/instructors')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);

    expect(collect($this->getJson('/api/v1/instructors')->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

it('forbids managing offerings without calendar.manage', function () {
    $semester = Semester::factory()->create();
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $course = Course::factory()->create();
    $teacher = instructor();
    actingAsRole(Role::Student); // holds no calendar.manage

    $this->getJson("/api/v1/semesters/{$semester->id}/offerings")->assertForbidden();
    $this->postJson("/api/v1/semesters/{$semester->id}/offerings", offeringPayload($course->id, $teacher->id))->assertForbidden();
    $this->getJson("/api/v1/offerings/{$offering->id}")->assertForbidden();
    $this->patchJson("/api/v1/offerings/{$offering->id}", [])->assertForbidden();
    $this->deleteJson("/api/v1/offerings/{$offering->id}")->assertForbidden();
    $this->getJson('/api/v1/instructors')->assertForbidden();
});

it('requires authentication to manage offerings', function () {
    $semester = Semester::factory()->create();
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);

    $this->getJson("/api/v1/semesters/{$semester->id}/offerings")->assertUnauthorized();
    $this->getJson("/api/v1/offerings/{$offering->id}")->assertUnauthorized();
    $this->getJson('/api/v1/instructors')->assertUnauthorized();
});
