<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Identity\Internal\Models\User;

/**
 * @extends Factory<Incomplete>
 */
class IncompleteFactory extends Factory
{
    /**
     * The relocated model (Modules\Grading\Internal\Models\Incomplete). Set explicitly because
     * the default resolver would guess App\Models.
     *
     * @var class-string<Incomplete>
     */
    protected $model = Incomplete::class;

    /**
     * A fresh, outstanding declaration at the configured minimum completion — the GradeFactory
     * precedent of minting the anchors a test wants a real row behind.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'declared_on_grade_sheet_id' => GradeSheet::factory(),
            'enrollment_id' => Enrollment::factory(),
            'declared_by' => User::factory(),
            'completion_percentage' => (int) config('grading.incomplete_min_completion'),
            'reason' => $this->faker->sentence(),
        ];
    }

    /** A resolved Incomplete — the Grade that settled it. */
    public function resolvedBy(int $gradeId): static
    {
        return $this->state(fn (): array => ['resolved_grade_id' => $gradeId]);
    }

    /** A converted Incomplete — Fail Withdrawal, at the given moment. */
    public function convertedAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (): array => ['converted_at' => $when]);
    }
}
