<?php

namespace Modules\Calendar\Internal\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;

/**
 * The Semester & Open Windows Dashboard Card's payload (phase 4.5, ticket 06): which Semester is
 * live right now, which of its period windows are open, and the next date due.
 *
 * Every answer is **read** from the authority that already owns it and nothing is re-derived, as
 * ADR-0024 requires of every Card: the live Semester through {@see AcademicCalendar} — the same
 * facade lookups Enrollment's Cards use, so the two can never disagree about which term is live —
 * and each window's openness from {@see SemesterSummary}'s own predicates, which delegate to the
 * one pure {@see SemesterCalendar}.
 *
 * The seeded world runs several Semesters at different points in their lifecycle at once, so "which
 * one is live" is a real question: the answer is the Semester **running** now, or, when no term is
 * running, the one whose **registration window** is open — the term about to start, which is the one
 * an administrator is configuring.
 *
 * The Card returns typed domain values only — enum values, ids, ISO dates, window keys — never a
 * pre-rendered label, so the SPA renders every window name bilingually itself.
 */
final readonly class SemesterWindowsCardBuilder
{
    /**
     * The dates an administrator's "what is next?" ranks over, as `key => the SemesterSummary
     * property holding it`: each change window's close, the term's own end, and the
     * grade-submission deadline that follows it (phase 4 ticket 09).
     *
     * This is a *deadline* list rather than a restatement of the period vocabulary — "when does the
     * live window close?" is a different question, and one {@see SemesterSummary::currentPeriodEndsAt()}
     * answers rather than this map.
     */
    private const DEADLINES = [
        'registration' => 'registrationClosesAt',
        'add_drop' => 'addDropEndsAt',
        'late_add' => 'lateAddEndsAt',
        'withdrawal' => 'withdrawalDeadlineAt',
        'semester_end' => 'endsAt',
        'grade_submission' => 'gradeSubmissionDeadlineAt',
    ];

    public function __construct(private AcademicCalendar $calendar) {}

    /**
     * @return array{
     *     semester: ?array{id: int, term: string, year: int, name_en: string, name_ar: string},
     *     current_period: ?string,
     *     open_windows: list<string>,
     *     next_deadline: ?array{window: string, at: string},
     * }
     */
    public function build(): array
    {
        // The two facade lookups, in the order the Card's question implies: the term that is running,
        // else the one being registered into.
        $semester = $this->calendar->running() ?? $this->calendar->openForRegistration();

        if ($semester === null) {
            return [
                'semester' => null,
                'current_period' => null,
                'open_windows' => [],
                'next_deadline' => null,
            ];
        }

        $now = now();

        return [
            'semester' => [
                'id' => $semester->id,
                'term' => $semester->term->value,
                'year' => $semester->year,
                'name_en' => $semester->nameEn,
                'name_ar' => $semester->nameAr,
            ],
            'current_period' => $semester->currentPeriod($now),
            'open_windows' => $this->openWindows($semester, $now),
            'next_deadline' => $this->nextDeadline($semester, $now),
        ];
    }

    /**
     * The window keys open at `$now` — plural on purpose: registration may still be open past the
     * semester start, so it overlaps add/drop. `currentPeriod()` names the single phase the Semester
     * reads as; this says everything that is actually open, read from the Semester's own predicates.
     *
     * @return list<string>
     */
    private function openWindows(SemesterSummary $semester, CarbonInterface $now): array
    {
        return array_values(array_filter([
            $semester->isRegistrationOpen($now) ? 'registration' : null,
            $semester->isInAddDropPeriod($now) ? 'add_drop' : null,
            $semester->isInLateAddPeriod($now) ? 'late_add' : null,
            $semester->isInWithdrawalPeriod($now) ? 'withdrawal' : null,
        ]));
    }

    /**
     * The soonest date still ahead, as `window => when`, or null once every date on this Semester
     * has passed (a finished term still shown because nothing newer is live yet).
     *
     * @return ?array{window: string, at: string}
     */
    private function nextDeadline(SemesterSummary $semester, CarbonInterface $now): ?array
    {
        $upcoming = null;

        foreach (self::DEADLINES as $window => $property) {
            /** @var CarbonImmutable $at */
            $at = $semester->{$property};

            if ($at->greaterThan($now) && ($upcoming === null || $at->lessThan($upcoming['at']))) {
                $upcoming = ['window' => $window, 'at' => $at];
            }
        }

        return $upcoming === null ? null : [
            'window' => $upcoming['window'],
            'at' => $upcoming['at']->toIso8601String(),
        ];
    }
}
