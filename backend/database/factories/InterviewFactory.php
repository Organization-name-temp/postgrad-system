<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admissions\Internal\Enums\InterviewStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\Interview).
     *
     * @var class-string<Interview>
     */
    protected $model = Interview::class;

    /**
     * A freshly booked interview: pending the applicant's confirmation, no reschedules
     * used, for an application resting in `interview_scheduled` and booked into a fresh
     * slot. Callers pass a real application/slot per case.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->interviewScheduled(),
            'interview_slot_id' => InterviewSlot::factory(),
            'status' => InterviewStatus::PendingConfirmation,
            'reschedule_count' => 0,
        ];
    }

    /** The applicant has confirmed the interview. */
    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => InterviewStatus::Confirmed]);
    }

    /** The applicant has already used their single reschedule. */
    public function rescheduledOnce(): static
    {
        return $this->state(fn (): array => ['reschedule_count' => Interview::MAX_RESCHEDULES]);
    }
}
