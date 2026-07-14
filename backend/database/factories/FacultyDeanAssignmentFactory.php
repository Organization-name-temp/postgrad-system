<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/**
 * @extends Factory<FacultyDeanAssignment>
 */
class FacultyDeanAssignmentFactory extends Factory
{
    /**
     * The relocated model (Modules\Identity\Internal\Models\FacultyDeanAssignment).
     *
     * @var class-string<FacultyDeanAssignment>
     */
    protected $model = FacultyDeanAssignment::class;

    /**
     * A routing row for a fresh Dean (R07 user) and an arbitrary opaque Faculty id.
     * Callers usually pass a real `user_id`/`faculty_id` per case; the default Dean
     * is created with the R07 role via `afterCreating` so an assignment factory used
     * alone still yields a genuine Dean.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->afterCreating(fn (User $user) => $user->assignRole(Role::Dean)),
            'faculty_id' => fake()->unique()->numberBetween(1, 100000),
        ];
    }
}
