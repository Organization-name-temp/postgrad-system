<?php

namespace Modules\Calendar\Internal\Support;

use Modules\Calendar\CalendarServiceProvider;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;

/**
 * The Eloquent-backed {@see AcademicCalendar}: reads Semesters from Calendar's own
 * store and maps each to a {@see SemesterSummary} so no Eloquent model crosses the
 * module seam (ADR-0012). Bound to the contract in
 * {@see CalendarServiceProvider}. The "open"/"running" lookups use
 * the model's SQL scopes — the mirror of the pure {@see SemesterCalendar} predicates
 * — so a query and a loaded model always agree.
 */
final class EloquentAcademicCalendar implements AcademicCalendar
{
    public function find(int $id): ?SemesterSummary
    {
        $semester = Semester::query()->find($id);

        return $semester === null ? null : $this->toSummary($semester);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, SemesterSummary>
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Semester::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Semester $semester): array => [
                (int) $semester->getKey() => $this->toSummary($semester),
            ])
            ->all();
    }

    public function openForRegistration(): ?SemesterSummary
    {
        $semester = Semester::query()->registrationOpen()->orderByDesc('year')->first();

        return $semester === null ? null : $this->toSummary($semester);
    }

    public function running(): ?SemesterSummary
    {
        $semester = Semester::query()->running()->orderByDesc('year')->first();

        return $semester === null ? null : $this->toSummary($semester);
    }

    public function nextAfter(int $semesterId): ?SemesterSummary
    {
        $semester = Semester::query()->find($semesterId);
        if ($semester === null) {
            return null;
        }

        $next = Semester::query()
            ->where('starts_at', '>', $semester->starts_at)
            ->orderBy('starts_at')
            ->first();

        return $next === null ? null : $this->toSummary($next);
    }

    private function toSummary(Semester $semester): SemesterSummary
    {
        return new SemesterSummary(
            id: $semester->getKey(),
            term: $semester->term,
            year: $semester->year,
            nameEn: $semester->name_en,
            nameAr: $semester->name_ar,
            startsAt: $semester->starts_at->toImmutable(),
            endsAt: $semester->ends_at->toImmutable(),
            registrationOpensAt: $semester->registration_opens_at->toImmutable(),
            registrationClosesAt: $semester->registration_closes_at->toImmutable(),
            addDropEndsAt: $semester->add_drop_ends_at->toImmutable(),
            lateAddEndsAt: $semester->late_add_ends_at->toImmutable(),
            withdrawalDeadlineAt: $semester->withdrawal_deadline_at->toImmutable(),
            gradeSubmissionDeadlineAt: $semester->grade_submission_deadline_at->toImmutable(),
        );
    }
}
