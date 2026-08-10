<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * The relocated model (Modules\Grading\Internal\Models\Grade). Set explicitly because the
     * default resolver would guess App\Models.
     *
     * @var class-string<Grade>
     */
    protected $model = Grade::class;

    /**
     * A recorded mark on a fresh Draft round. The letter and points come from the ADR-0021
     * scale through {@see Grade::derivedFrom()} — the same single derivation the API uses — so
     * a fixture can never carry a letter its mark would not produce.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge([
            'grade_sheet_id' => GradeSheet::factory(),
            'enrollment_id' => Enrollment::factory(),
        ], Grade::derivedFrom(82.50));
    }

    /** A Grade for a specific mark, with its letter and points re-derived from the scale. */
    public function withMark(float $mark): static
    {
        return $this->state(fn (): array => Grade::derivedFrom($mark));
    }
}
