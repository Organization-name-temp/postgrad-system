<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Enums\DegreeLevel;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * The relocated Program model (Modules\Catalog\Internal\Models\Program). Set
     * explicitly because the default resolver would guess App\Models\Program.
     *
     * @var class-string<Program>
     */
    protected $model = Program::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PRG-###')),
            'name_en' => fake()->randomElement(['MSc', 'PhD']).' in '.fake()->unique()->words(2, true),
            'name_ar' => 'برنامج '.fake()->unique()->word(),
            'degree_level' => fake()->randomElement(DegreeLevel::cases()),
            'prep_required_ch' => 6,
            'core_required_ch' => 6,
            'elective_required_ch' => 9,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
