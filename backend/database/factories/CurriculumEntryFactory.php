<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

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
            'preparatory_requirement_role' => function (array $attributes): ?PreparatoryRequirementRole {
                $category = $attributes['category'] instanceof CourseCategory
                    ? $attributes['category']
                    : CourseCategory::tryFrom((string) $attributes['category']);

                return $category === CourseCategory::Preparatory
                    ? fake()->randomElement(PreparatoryRequirementRole::cases())
                    : null;
            },
            'ordinal' => 0,
        ];
    }
}
