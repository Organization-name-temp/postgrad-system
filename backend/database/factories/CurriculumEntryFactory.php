<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Enums\CourseCategory;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;

/**
 * @extends Factory<CurriculumEntry>
 */
class CurriculumEntryFactory extends Factory
{
    /**
     * The relocated CurriculumEntry model
     * (Modules\Catalog\Internal\Models\CurriculumEntry). Set explicitly because the
     * default resolver would guess App\Models\CurriculumEntry.
     *
     * @var class-string<CurriculumEntry>
     */
    protected $model = CurriculumEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'course_id' => Course::factory(),
            'category' => fake()->randomElement(CourseCategory::cases()),
            'ordinal' => 0,
        ];
    }
}
