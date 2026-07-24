<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Identity\Internal\Models\User;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\CourseOffering). Set
     * explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<CourseOffering>
     */
    protected $model = CourseOffering::class;

    /**
     * A default Offering wiring up a fresh Semester, Course and Instructor. The three
     * references are opaque ids (no cross-module FK — ADR-0012); factories may still
     * lean on the owning modules' factories to produce real rows for a feature test.
     * The instructor is a plain User here — API creation validates the R04 role, but a
     * directly-built row does not need it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'course_id' => Course::factory(),
            'instructor_id' => User::factory(),
            'capacity' => fake()->optional()->numberBetween(10, 40),
        ];
    }

    /** An Offering with no seat cap. */
    public function uncapped(): static
    {
        return $this->state(fn (): array => ['capacity' => null]);
    }
}
