<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;
use Modules\Enrollment\Internal\Support\RemediationReconciler;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

uses(RefreshDatabase::class);

it('notifies the capacity reviewer when replacement exemption closes a request', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $request = RegistrationRequest::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $offering->semester_id,
        'status' => RegistrationStatus::AwaitingCapacityOverride,
    ]);
    $releasedLine = RegistrationRequestLine::factory()->create([
        'registration_request_id' => $request->id,
        'course_offering_id' => $offering->id,
    ]);
    $otherCourse = Course::factory()->create();
    $otherOffering = CourseOffering::factory()->create(['course_id' => $otherCourse->id, 'semester_id' => $offering->semester_id]);
    $otherLine = RegistrationRequestLine::factory()->create(['registration_request_id' => $request->id, 'course_offering_id' => $otherOffering->id]);
    foreach ([[$releasedLine, $offering, $course], [$otherLine, $otherOffering, $otherCourse]] as [$line, $candidateOffering, $candidateCourse]) {
        CapacityOverrideRequirement::query()->create([
            'registration_request_id' => $request->id,
            'registration_request_line_id' => $line->id,
            'course_offering_id' => $candidateOffering->id,
            'course_id' => $candidateCourse->id,
            'department_id' => $candidateCourse->department_id,
            'state' => 'Pending',
        ]);
    }

    $headId = 9876;
    $otherHeadId = 9877;
    $heads = mock(DepartmentHeadAssignments::class, function (MockInterface $mock) use ($course, $headId, $otherCourse, $otherHeadId): void {
        $mock->shouldReceive('headsForDepartment')->once()->with($course->department_id)->andReturn([$headId]);
        $mock->shouldReceive('headsForDepartment')->once()->with($otherCourse->department_id)->andReturn([$otherHeadId]);
    });
    $notifier = Mockery::spy(Notifier::class);
    $reconciler = new RemediationReconciler(
        app(AcademicCalendar::class),
        app(CourseCatalog::class),
        app(StudentDirectory::class),
        app(StudentGuard::class),
        $heads,
        $notifier,
    );

    $reconciler->releaseByExemption($student->id, $course->id, 44);

    expect($request->fresh()->status)->toBe(RegistrationStatus::Rejected);
    $notifier->shouldHaveReceived('notifyOnce')
        ->with($headId, NotificationType::AcademicRequestClosed, "remediation-release:44:registration:{$request->id}:{$headId}", Mockery::type('array'))
        ->once();
    $notifier->shouldHaveReceived('notifyOnce')
        ->with($otherHeadId, NotificationType::AcademicRequestClosed, "remediation-release:44:registration:{$request->id}:{$otherHeadId}", Mockery::type('array'))
        ->once();
});
