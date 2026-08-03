<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * The relocated model (Modules\Calendar\Internal\Models\Semester). Set
     * explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<Semester>
     */
    protected $model = Semester::class;

    /**
     * Defaults to a well-ordered Semester whose registration window is open now and
     * whose term has not yet started. Windows are anchored relative to `now()` so a
     * time-frozen test (`travelTo`) lands in whichever period it asserts. The year is
     * drawn uniquely so a batch never trips the (term, year) unique constraint.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(
            [
                'term' => fake()->randomElement(Term::cases()),
                'year' => fake()->unique()->numberBetween(2000, 2100),
            ],
            $this->window(
                registrationOpens: '-1 week',
                registrationCloses: '+1 week',
                starts: '+2 weeks',
                addDropEnds: '+4 weeks',
                lateAddEnds: '+5 weeks',
                withdrawalDeadline: '+10 weeks',
                ends: '+16 weeks',
                gradeSubmissionDeadline: '+18 weeks',
            ),
        );
    }

    /** Registration open now, term not yet started (the default). */
    public function registrationOpen(): static
    {
        return $this->state(fn (): array => $this->window(
            registrationOpens: '-1 week',
            registrationCloses: '+1 week',
            starts: '+2 weeks',
            addDropEnds: '+4 weeks',
            lateAddEnds: '+5 weeks',
            withdrawalDeadline: '+10 weeks',
            ends: '+16 weeks',
            gradeSubmissionDeadline: '+18 weeks',
        ));
    }

    /** Registration not yet open — the whole calendar is still ahead. */
    public function upcoming(): static
    {
        return $this->state(fn (): array => $this->window(
            registrationOpens: '+2 weeks',
            registrationCloses: '+4 weeks',
            starts: '+6 weeks',
            addDropEnds: '+8 weeks',
            lateAddEnds: '+9 weeks',
            withdrawalDeadline: '+14 weeks',
            ends: '+20 weeks',
            gradeSubmissionDeadline: '+22 weeks',
        ));
    }

    /** Term running now, registration closed, inside the withdrawal window. */
    public function running(): static
    {
        return $this->state(fn (): array => $this->window(
            registrationOpens: '-10 weeks',
            registrationCloses: '-8 weeks',
            starts: '-6 weeks',
            addDropEnds: '-4 weeks',
            lateAddEnds: '-3 weeks',
            withdrawalDeadline: '+2 weeks',
            ends: '+6 weeks',
            gradeSubmissionDeadline: '+8 weeks',
        ));
    }

    /** Term finished — the whole calendar is in the past. */
    public function ended(): static
    {
        return $this->state(fn (): array => $this->window(
            registrationOpens: '-30 weeks',
            registrationCloses: '-28 weeks',
            starts: '-26 weeks',
            addDropEnds: '-24 weeks',
            lateAddEnds: '-23 weeks',
            withdrawalDeadline: '-18 weeks',
            ends: '-12 weeks',
            gradeSubmissionDeadline: '-10 weeks',
        ));
    }

    /**
     * Build the eight period columns from human-readable offsets relative to `now()`.
     *
     * @return array<string, Carbon>
     */
    private function window(
        string $registrationOpens,
        string $registrationCloses,
        string $starts,
        string $addDropEnds,
        string $lateAddEnds,
        string $withdrawalDeadline,
        string $ends,
        string $gradeSubmissionDeadline,
    ): array {
        return [
            'registration_opens_at' => now()->modify($registrationOpens),
            'registration_closes_at' => now()->modify($registrationCloses),
            'starts_at' => now()->modify($starts),
            'add_drop_ends_at' => now()->modify($addDropEnds),
            'late_add_ends_at' => now()->modify($lateAddEnds),
            'withdrawal_deadline_at' => now()->modify($withdrawalDeadline),
            'ends_at' => now()->modify($ends),
            'grade_submission_deadline_at' => now()->modify($gradeSubmissionDeadline),
        ];
    }
}
