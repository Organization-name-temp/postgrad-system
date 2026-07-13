<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Models\Faculty;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    /**
     * The relocated Faculty model (Modules\Catalog\Internal\Models\Faculty). Set
     * explicitly because the default resolver would guess App\Models\Faculty.
     *
     * @var class-string<Faculty>
     */
    protected $model = Faculty::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FAC###')),
            'name_en' => 'Faculty of '.fake()->unique()->words(2, true),
            'name_ar' => 'كلية '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
