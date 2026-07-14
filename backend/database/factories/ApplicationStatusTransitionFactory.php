<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;

/**
 * @extends Factory<ApplicationStatusTransition>
 */
class ApplicationStatusTransitionFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\ApplicationStatusTransition).
     * Set explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<ApplicationStatusTransition>
     */
    protected $model = ApplicationStatusTransition::class;

    /**
     * A single finalize move (EmailVerified → UnderSAReview) at "now" — the shape
     * of a typical timeline row. Callers override `application_id`, the statuses,
     * and `occurred_at` (e.g. to place an entry into review in the past so the queue
     * derives a non-zero age). `occurred_at` is set because the model manages no
     * timestamps.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'from_status' => ApplicationStatus::EmailVerified,
            'to_status' => ApplicationStatus::UnderSAReview,
            'actor_user_id' => null,
            'note' => null,
            'occurred_at' => Carbon::now(),
        ];
    }

    /** Record entry into a given status at a given moment (drives queue aging). */
    public function enteredAt(ApplicationStatus $status, Carbon $when): static
    {
        return $this->state(fn (): array => [
            'to_status' => $status,
            'occurred_at' => $when,
        ]);
    }
}
