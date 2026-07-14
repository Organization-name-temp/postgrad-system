<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\User;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\Application). Set
     * explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<Application>
     */
    protected $model = Application::class;

    /**
     * Defaults to a Draft owned by a fresh User in a fresh open Cycle — the state
     * self-registration produces (ticket 03).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'admission_cycle_id' => AdmissionCycle::factory()->open(),
            'status' => ApplicationStatus::Draft,
        ];
    }
}
