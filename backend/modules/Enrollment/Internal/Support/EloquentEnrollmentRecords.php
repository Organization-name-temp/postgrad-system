<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Enrollment\EnrollmentServiceProvider;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\OfferingSummary;

/**
 * The Eloquent-backed {@see EnrollmentRecords}. It reads Enrollment's own
 * {@see CourseOffering} and {@see Enrollment} models and maps each to a DTO, so neither model
 * crosses the seam (ADR-0012). Bound to the interface in {@see EnrollmentServiceProvider}.
 *
 * The gradable filter is applied **in the database** from the set
 * {@see CourseStatus::isGradable()} judges gradable, rather than fetching the whole roster and
 * filtering in PHP — one query per roster, and the enum stays the single authority on what
 * "gradable" means.
 */
class EloquentEnrollmentRecords implements EnrollmentRecords
{
    /**
     * The Calendar facade is read only to order the Offering lists chronologically — the
     * Semester reference is an opaque id with no FK to join on (ADR-0012), so "most recent
     * Semester first" cannot be expressed as a SQL ordering.
     */
    public function __construct(private readonly AcademicCalendar $calendar) {}

    public function findOffering(int $offeringId): ?OfferingSummary
    {
        $offering = CourseOffering::query()->find($offeringId);

        return $offering === null ? null : $this->toOfferingSummary($offering);
    }

    /**
     * @return list<OfferingSummary>
     */
    public function offeringsTaughtBy(int $instructorUserId): array
    {
        return $this->offerings(fn (Builder $query): Builder => $query->where('instructor_id', $instructorUserId));
    }

    /**
     * @return list<OfferingSummary>
     */
    public function allOfferings(): array
    {
        return $this->offerings();
    }

    /**
     * Offerings as DTOs, most recent Semester first — the one ordering both Offering lists share,
     * because they populate the same screen (the Instructor's own, and ticket 09's central
     * picker). The optional constraint is the only thing that differs between them.
     *
     * "Most recent" is the Semester's **`starts_at`**, resolved through `Calendar\Public` and
     * sorted in PHP, because the Semester reference is an opaque id with no FK to join on
     * (ADR-0012) — and because a descending id is not the same ordering: a back-filled past
     * term (Phase 4's completed Semesters, seeded after Phase 3's) carries the *higher* id while
     * being the *older* term, which would have listed it above a future one. Offerings whose
     * Semester no longer resolves sort last rather than disappearing, and the offering id breaks
     * ties — **negated**, because the whole sort is descending and two Offerings of one Semester
     * should still read in the order they were created.
     *
     * @param  (callable(Builder<CourseOffering>): Builder<CourseOffering>)|null  $constrain
     * @return list<OfferingSummary>
     */
    private function offerings(?callable $constrain = null): array
    {
        $query = CourseOffering::query()->orderBy('id');

        /** @var Collection<int, CourseOffering> $offerings */
        $offerings = ($constrain === null ? $query : $constrain($query))->get();

        $semesters = $this->calendar->findMany(
            $offerings->pluck('semester_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );

        return $offerings
            ->sortByDesc(fn (CourseOffering $o): array => [
                ($semesters[(int) $o->semester_id] ?? null)?->startsAt->getTimestamp() ?? PHP_INT_MIN,
                -((int) $o->getKey()),
            ])
            ->map(fn (CourseOffering $o): OfferingSummary => $this->toOfferingSummary($o))
            ->values()
            ->all();
    }

    /**
     * @return list<EnrollmentSummary>
     */
    public function gradableEnrollmentsOf(int $offeringId): array
    {
        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->with('offering')
            ->where('course_offering_id', $offeringId)
            ->whereIn('course_status', self::gradableStatuses())
            ->orderBy('id')
            ->get();

        return $enrollments->map(fn (Enrollment $e): EnrollmentSummary => $this->toEnrollmentSummary($e))
            ->values()
            ->all();
    }

    /**
     * @return list<EnrollmentSummary>
     */
    public function enrollmentsForStudent(int $studentId): array
    {
        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->with('offering')
            ->where('student_id', $studentId)
            ->orderBy('id')
            ->get();

        return $enrollments->map(fn (Enrollment $e): EnrollmentSummary => $this->toEnrollmentSummary($e))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $offeringIds
     * @return array<int, int>
     */
    public function gradableCountsFor(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return Enrollment::query()
            ->selectRaw('course_offering_id, count(*) as aggregate')
            ->whereIn('course_offering_id', $offeringIds)
            ->whereIn('course_status', self::gradableStatuses())
            ->groupBy('course_offering_id')
            ->pluck('aggregate', 'course_offering_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function findEnrollment(int $enrollmentId): ?EnrollmentSummary
    {
        $enrollment = Enrollment::query()->with('offering')->find($enrollmentId);

        return $enrollment === null ? null : $this->toEnrollmentSummary($enrollment);
    }

    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, EnrollmentSummary>
     */
    public function findManyEnrollments(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()->with('offering')->whereKey($enrollmentIds)->get();

        return $enrollments
            ->mapWithKeys(fn (Enrollment $e): array => [
                (int) $e->getKey() => $this->toEnrollmentSummary($e),
            ])
            ->all();
    }

    public function recordOutcome(int $enrollmentId, ?bool $earned): void
    {
        Enrollment::query()->whereKey($enrollmentId)->update(['earned' => $earned]);
    }

    public function markIncomplete(int $enrollmentId): void
    {
        $enrollment = Enrollment::query()->findOrFail($enrollmentId);

        $enrollment->update([
            'pre_incomplete_status' => $enrollment->course_status,
            'course_status' => CourseStatus::Incomplete,
        ]);
    }

    public function resolveIncomplete(int $enrollmentId, bool $earned): void
    {
        $enrollment = Enrollment::query()->findOrFail($enrollmentId);

        // The invariant markIncomplete() establishes: an Enrollment only ever reaches this
        // method while Incomplete, and markIncomplete() always set this column the moment it
        // did — so it is never null here.
        $enrollment->update([
            'course_status' => $enrollment->pre_incomplete_status,
            'pre_incomplete_status' => null,
            'earned' => $earned,
        ]);
    }

    public function convertIncompleteToFailWithdrawal(int $enrollmentId): void
    {
        Enrollment::query()->whereKey($enrollmentId)->update([
            'course_status' => CourseStatus::FailWithdrawal,
            'pre_incomplete_status' => null,
        ]);
    }

    /**
     * The DTO for one Enrollment, resolving its Offering's Course/Semester ids in the same
     * read (eager-loaded by every caller above, so this never triggers a fresh query) —
     * ticket 04's grouping keys, alongside Enrollment's own counting judgement so Grading
     * reads Appendix D rather than re-deriving it. `course_offering_id` carries a real FK
     * (an intra-module reference) and an Offering referenced by an Enrollment can never be
     * deleted (`OfferingController::destroy()`'s guard), so `$enrollment->offering` is never
     * null here — read directly, the same as every other guaranteed-present field below.
     */
    private function toEnrollmentSummary(Enrollment $enrollment): EnrollmentSummary
    {
        $offering = $enrollment->offering;

        return new EnrollmentSummary(
            id: (int) $enrollment->getKey(),
            studentId: (int) $enrollment->student_id,
            courseOfferingId: (int) $enrollment->course_offering_id,
            classification: $enrollment->classification->value,
            courseStatus: $enrollment->course_status->value,
            creditHours: (int) $enrollment->credit_hours,
            courseId: (int) $offering->course_id,
            semesterId: (int) $offering->semester_id,
            countsTowardCgpa: $enrollment->course_status->counting()->cgpa,
            earned: $enrollment->earned,
        );
    }

    private function toOfferingSummary(CourseOffering $offering): OfferingSummary
    {
        return new OfferingSummary(
            id: (int) $offering->getKey(),
            semesterId: (int) $offering->semester_id,
            courseId: (int) $offering->course_id,
            instructorId: (int) $offering->instructor_id,
        );
    }

    /**
     * The stored values of the gradable statuses — derived from the enum so adding or
     * re-judging a case never leaves this query behind.
     *
     * @return list<string>
     */
    private static function gradableStatuses(): array
    {
        return array_values(array_map(
            fn (CourseStatus $status): string => $status->value,
            array_filter(CourseStatus::cases(), fn (CourseStatus $status): bool => $status->isGradable()),
        ));
    }
}
