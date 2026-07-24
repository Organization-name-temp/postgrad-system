<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Identity\Internal\Models\Student;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\Enrollment). Set explicitly
     * because the default resolver would guess App\Models.
     *
     * @var class-string<Enrollment>
     */
    protected $model = Enrollment::class;

    /**
     * A default active Core Enrollment wiring up a fresh Student anchor and Offering. The
     * student is an opaque id (no cross-module FK — ADR-0012); the Offering is a real
     * same-module row. `credit_hours` snapshots a plausible 3-CH load.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'classification' => EnrollmentClassification::Core,
            'course_status' => CourseStatus::Regular,
            'credit_hours' => 3,
        ];
    }

    /** An Enrollment in a specific classification (the snapshot the caps read). */
    public function classifiedAs(EnrollmentClassification $classification): static
    {
        return $this->state(fn (): array => ['classification' => $classification]);
    }

    /** An Enrollment in a specific status (Regular / Retake / Audit / W). */
    public function withStatus(CourseStatus $status): static
    {
        return $this->state(fn (): array => ['course_status' => $status]);
    }
}
