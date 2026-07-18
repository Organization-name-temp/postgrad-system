<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Models\AdmissionCycle;

/**
 * @extends Factory<AdmissionCycle>
 */
class AdmissionCycleFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\AdmissionCycle).
     * Set explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<AdmissionCycle>
     */
    protected $model = AdmissionCycle::class;

    /**
     * Defaults to a currently-open Cycle. The year is drawn uniquely so a batch
     * never trips the (intake_term, year) unique constraint.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $term = fake()->randomElement(IntakeTerm::cases());
        $year = fake()->unique()->numberBetween(2000, 2100);

        return [
            'intake_term' => $term,
            'year' => $year,
            'name_en' => $term->value.' '.$year,
            'name_ar' => ($term === IntakeTerm::Fall ? 'خريف ' : 'ربيع ').$year,
            'opens_at' => now()->subWeek(),
            'closes_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    /** Active and inside its window — currently open. */
    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
            'opens_at' => now()->subWeek(),
            'closes_at' => now()->addMonth(),
        ]);
    }

    /** Deactivated — closed regardless of its window. */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /** Active, but its window has already elapsed. */
    public function past(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
            'opens_at' => now()->subMonths(3),
            'closes_at' => now()->subMonth(),
        ]);
    }

    /** Active, but its window has not started yet. */
    public function future(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
            'opens_at' => now()->addMonth(),
            'closes_at' => now()->addMonths(3),
        ]);
    }
}
