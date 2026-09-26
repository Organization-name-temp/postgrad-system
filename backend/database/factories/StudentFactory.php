<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * The relocated model (Modules\Identity\Internal\Models\Student). Set explicitly
     * because the default resolver would guess App\Models.
     *
     * @var class-string<Student>
     */
    protected $model = Student::class;

    /**
     * A minted Student with a unique University ID and its own fresh owner and advisor.
     * The identity snapshot mirrors the shape the acceptance mint copies from an
     * Application; callers override `advisor_id` when a test needs a specific advisor's
     * advisee load (e.g. the least-loaded-advisor selection).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'university_id' => '2026-PRG-'.fake()->unique()->numerify('####'),
            'program_id' => fake()->numberBetween(1, 1000),
            'curriculum_version_id' => fake()->numberBetween(1, 1000),
            'curriculum_version_binding_source' => CurriculumVersionBindingSource::AdmissionTime,
            'enrollment_term' => 'Fall 2026',
            'admission_cycle_id' => fake()->numberBetween(1, 100),
            'advisor_id' => User::factory(),
            'name_en' => fake()->name(),
            'name_ar' => 'سارة المتقدمة',
            'national_id' => fake()->unique()->numerify('##############'),
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-22 years')->format('Y-m-d'),
            'nationality' => 'EG',
            'gender' => 'Female',
            'admitted_at' => now(),
        ];
    }
}
