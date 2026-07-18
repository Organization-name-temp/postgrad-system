<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admissions\Internal\Enums\InterviewMode;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/**
 * @extends Factory<InterviewSlot>
 */
class InterviewSlotFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\InterviewSlot).
     *
     * @var class-string<InterviewSlot>
     */
    protected $model = InterviewSlot::class;

    /**
     * An open, in-person 30-minute slot a few days out, assigned to a fresh faculty
     * User (an Instructor — the interviewer-eligible role). Callers pass a real
     * `interviewer_id` per case where the assigned interviewer matters (e.g. the
     * row-level evaluation tests).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'duration_minutes' => 30,
            'interviewer_id' => User::factory()->afterCreating(
                fn (User $user) => $user->assignRole(Role::Instructor),
            ),
            'mode' => InterviewMode::InPerson,
            'location' => 'Building C, Room 204',
            'meeting_link' => null,
        ];
    }

    /** Assign this slot to a specific interviewer. */
    public function forInterviewer(User $interviewer): static
    {
        return $this->state(fn (): array => ['interviewer_id' => $interviewer->id]);
    }

    /** A virtual slot — a meeting link instead of a physical location. */
    public function virtual(): static
    {
        return $this->state(fn (): array => [
            'mode' => InterviewMode::Virtual,
            'location' => null,
            'meeting_link' => 'https://meet.ejust.edu.eg/'.fake()->uuid(),
        ]);
    }
}
