<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\PreviousDegree;
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
     * Defaults to an empty Draft owned by a fresh **unverified** User in a fresh
     * open Cycle — exactly what self-registration produces (ticket 03): the User
     * exists but has not yet confirmed its email, and the Step-1 snapshot is blank
     * until the wizard fills it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->unverified(),
            'admission_cycle_id' => AdmissionCycle::factory()->open(),
            'status' => ApplicationStatus::Draft,
        ];
    }

    /**
     * Fill the Step-1 personal-info snapshot with complete, valid data — a Draft
     * ready to submit. Individual fields are overridable per test (e.g. a specific
     * national_id to exercise the per-cycle uniqueness rule).
     */
    public function withPersonalInfo(): static
    {
        return $this->state(fn (): array => [
            'name_en' => fake()->name(),
            'name_ar' => 'سارة المتقدمة',
            'national_id' => fake()->unique()->numerify('##############'),
            'phone' => fake()->numerify('+20##########'),
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-22 years')->format('Y-m-d'),
            'nationality' => 'Egyptian',
            'gender' => Gender::Female,
            'address_country' => 'Egypt',
            'address_city' => fake()->city(),
            'address_street' => fake()->streetAddress(),
            'previous_university' => 'Cairo University',
            'graduation_certificate_name' => 'BSc in Computer Science',
            'graduation_date' => fake()->dateTimeBetween('-6 years', '-1 year')->format('Y-m-d'),
            'previous_degree' => PreviousDegree::BSc,
            'previous_major' => 'Computer Science',
            'graduation_gpa' => 3.50,
            'graduation_cgpa' => 3.45,
            'gpa_scale' => GpaScale::FourPointZero,
            'is_ejust_graduate' => false,
            'applying_for_degree' => 'MSc',
            'english_test' => EnglishTest::Ielts,
            'english_score' => 7.50,
            'native_arabic_speaker' => true,
        ]);
    }

    /**
     * A Step-1-submitted Application awaiting email verification (Submitted). Its
     * owner's email is unverified (the default), so the verification link is the
     * next gate.
     */
    public function submitted(): static
    {
        return $this->withPersonalInfo()->state(fn (): array => [
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
