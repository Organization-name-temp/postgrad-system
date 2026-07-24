<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Internal\Models\Student;

/**
 * @extends Factory<RegistrationRequest>
 */
class RegistrationRequestFactory extends Factory
{
    /**
     * The relocated model (Modules\Enrollment\Internal\Models\RegistrationRequest). Set
     * explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<RegistrationRequest>
     */
    protected $model = RegistrationRequest::class;

    /**
     * A default Pending Initial request wiring up a fresh Student anchor and Semester. The
     * two references are opaque ids (no cross-module FK — ADR-0012); factories may still
     * lean on the owning modules' factories to produce real rows for a feature test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'semester_id' => Semester::factory(),
            'kind' => RegistrationKind::Initial,
            'status' => RegistrationStatus::Pending,
        ];
    }

    /** A Pending request awaiting the advisor's decision (the default). */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => RegistrationStatus::Pending]);
    }

    /** A Rejected request — the state a fresh cart may replace while the window is open. */
    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => RegistrationStatus::Rejected]);
    }
}
