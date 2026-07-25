<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;

/**
 * @extends Factory<GradeSheet>
 */
class GradeSheetFactory extends Factory
{
    /**
     * The relocated model (Modules\Grading\Internal\Models\GradeSheet). Set explicitly
     * because the default resolver would guess App\Models.
     *
     * @var class-string<GradeSheet>
     */
    protected $model = GradeSheet::class;

    /**
     * A Draft Initial round for a fresh Offering. The Offering is an opaque reference with no
     * FK (ADR-0012), but a test wants a real one behind it, so the factory mints one — the
     * EnrollmentFactory precedent for its student anchor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'kind' => GradeSheetKind::Initial,
            'status' => GradeSheetStatus::Draft,
        ];
    }

    /** A round in a specific state (only Draft is reachable through the API in ticket 01). */
    public function inStatus(GradeSheetStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
