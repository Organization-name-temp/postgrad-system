<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;

/**
 * @extends Factory<RegistrationRequestLine>
 */
class RegistrationRequestLineFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\RegistrationRequestLine).
     * Set explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<RegistrationRequestLine>
     */
    protected $model = RegistrationRequestLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'registration_request_id' => RegistrationRequest::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'mode' => RegistrationMode::Regular,
        ];
    }

    /** An Audit line — sat without credit. */
    public function audit(): static
    {
        return $this->state(fn (): array => ['mode' => RegistrationMode::Audit]);
    }
}
