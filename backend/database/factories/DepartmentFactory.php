<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * The relocated Department model (Modules\Catalog\Internal\Models\Department).
     * Set explicitly because the default resolver would guess App\Models\Department.
     *
     * @var class-string<Department>
     */
    protected $model = Department::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'code' => strtoupper(fake()->unique()->bothify('DEP###')),
            'name_en' => 'Department of '.fake()->unique()->words(2, true),
            'name_ar' => 'قسم '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
