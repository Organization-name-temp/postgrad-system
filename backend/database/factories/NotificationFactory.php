<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Notifications\Internal\Models\Notification;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The relocated model (Modules\Notifications\Internal\Models\Notification).
     * Set explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<Notification>
     */
    protected $model = Notification::class;

    /**
     * An unread notification. `user_id` is left to the caller (a real recipient
     * must exist); created_at is set because the model manages no timestamps.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(NotificationType::cases()),
            'data' => [],
            'read_at' => null,
            'created_at' => Carbon::now(),
        ];
    }

    /** Already read (read_at set). */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => ['read_at' => Carbon::now()]);
    }
}
