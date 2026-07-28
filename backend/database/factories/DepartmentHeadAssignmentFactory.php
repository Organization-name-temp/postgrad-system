<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/**
 * @extends Factory<DepartmentHeadAssignment>
 */
class DepartmentHeadAssignmentFactory extends Factory
{
    /**
     * The relocated model (Modules\Identity\Internal\Models\DepartmentHeadAssignment).
     *
     * @var class-string<DepartmentHeadAssignment>
     */
    protected $model = DepartmentHeadAssignment::class;

    /**
     * A routing row for a fresh Department Head (R06 user) and an arbitrary opaque
     * Department id. Callers usually pass a real `user_id`/`department_id` per case;
     * the default Head is created with the R06 role via `afterCreating` so an
     * assignment factory used alone still yields a genuine Head.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->afterCreating(fn (User $user) => $user->assignRole(Role::DepartmentHead)),
            'department_id' => fake()->unique()->numberBetween(1, 100000),
        ];
    }

    /**
     * The university-wide Preparatory scope — the NULL-Department assignment that
     * catches PRE401–404, whose Courses belong to no Department (ADR-0010/0023).
     *
     * @return Factory<DepartmentHeadAssignment>
     */
    public function universityWide(): Factory
    {
        return $this->state(fn (): array => ['department_id' => null]);
    }
}
