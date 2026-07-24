<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\Enrollment;

/**
 * @extends Factory<AttendanceMark>
 */
class AttendanceMarkFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\AttendanceMark). Set explicitly
     * because the default resolver would guess App\Models.
     *
     * @var class-string<AttendanceMark>
     */
    protected $model = AttendanceMark::class;

    /**
     * A default present mark wiring up a fresh session and enrollment. Feature tests pass the
     * concrete pair; this keeps a standalone build valid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'enrollment_id' => Enrollment::factory(),
            'present' => true,
        ];
    }

    /** An absence mark. */
    public function absent(): static
    {
        return $this->state(fn (): array => ['present' => false]);
    }
}
