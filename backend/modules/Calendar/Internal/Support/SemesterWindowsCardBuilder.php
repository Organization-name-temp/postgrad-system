<?php

namespace Modules\Calendar\Internal\Support;

use Carbon\CarbonInterface;
use Modules\Calendar\Public\AcademicCalendar;

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
    /** @var array<string, string> */
    private const DEADLINE_WINDOWS = [
        'registration_closes_at' => 'registration',
        'add_drop_ends_at' => 'add_drop',
        'late_add_ends_at' => 'late_add',
        'withdrawal_deadline_at' => 'withdrawal',
        'ends_at' => 'semester_end',
        'grade_submission_deadline_at' => 'grade_submission',
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
            'open_windows' => $semester->openWindows($now),
            'next_deadline' => $this->formatDeadline($semester->nextDeadline($now)),
        ];
    }

    /**
     * @param  ?array{key: string, at: CarbonInterface}  $deadline
     * @return ?array{window: string, at: string}
     */
    private function formatDeadline(?array $deadline): ?array
    {
        if ($deadline === null) {
            return null;
        }

        $window = self::DEADLINE_WINDOWS[$deadline['key']] ?? null;
        if ($window === null) {
            throw new \LogicException("Unknown Semester deadline key [{$deadline['key']}].");
        }

        return [
            'window' => $window,
            'at' => $deadline['at']->toIso8601String(),
        ];
    }
}
