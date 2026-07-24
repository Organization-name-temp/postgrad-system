<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Public\Enums\CourseLevel;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * The relocated Course model (Modules\Catalog\Internal\Models\Course). Set
     * explicitly because the default resolver would guess App\Models\Course.
     *
     * @var class-string<Course>
     */
    protected $model = Course::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Build a balanced breakdown (lecture + tutorial + lab = total) so every
        // factory-made Course satisfies the domain invariant by default.
        $lecture = fake()->numberBetween(1, 3);
        $tutorial = fake()->numberBetween(0, 2);
        $lab = fake()->numberBetween(0, 2);

        return [
            'department_id' => Department::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CRS###')),
            'name_en' => fake()->unique()->words(3, true),
            'name_ar' => 'مقرر '.fake()->unique()->word(),
            'level' => fake()->randomElement(CourseLevel::cases()),
            // Most Courses are not research courses; the Research Type is opt-in
            // (see the withResearchType() state).
            'research_type' => null,
            'lecture_ch' => $lecture,
            'tutorial_ch' => $tutorial,
            'lab_ch' => $lab,
            'total_ch' => $lecture + $tutorial + $lab,
            'is_active' => true,
        ];
    }

    /** A university-wide Course — owned by no Department (the shared Preparatory courses). */
    public function universityWide(): static
    {
        return $this->state(fn (array $attributes): array => ['department_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /** A Research Course carrying a Research Type — PBL by default (the MSc match). */
    public function withResearchType(ResearchType $type = ResearchType::Pbl): static
    {
        return $this->state(fn (array $attributes): array => ['research_type' => $type]);
    }
}
