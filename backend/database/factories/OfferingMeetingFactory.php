<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;

/**
 * @extends Factory<OfferingMeeting>
 */
class OfferingMeetingFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\OfferingMeeting). Set
     * explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<OfferingMeeting>
     */
    protected $model = OfferingMeeting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'weekday' => fake()->randomElement(Weekday::cases()),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
        ];
    }

    /** A meeting at a specific weekday and clock window (the readable test builder). */
    public function slot(Weekday $weekday, string $startsAt, string $endsAt): static
    {
        return $this->state(fn (): array => [
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
