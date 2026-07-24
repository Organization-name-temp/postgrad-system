<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\ClassSession). Set explicitly
     * because the default resolver would guess App\Models.
     *
     * @var class-string<ClassSession>
     */
    protected $model = ClassSession::class;

    /**
     * A default, not-cancelled session on a fresh Offering. Feature tests usually pass a
     * concrete offering and date; this keeps a standalone build valid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'session_date' => '2026-09-07',
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'cancelled_at' => null,
        ];
    }

    /** A cancelled session — it counts in no attendance denominator. */
    public function cancelled(): static
    {
        return $this->state(fn (): array => ['cancelled_at' => now()]);
    }

    /** A session on a specific date. */
    public function on(string $date): static
    {
        return $this->state(fn (): array => ['session_date' => $date]);
    }
}
