<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Database\Eloquent\Collection;
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
        /** @var Collection<int, CourseOffering> $offerings */
        $offerings = CourseOffering::query()
            ->where('instructor_id', $instructorUserId)
            ->orderByDesc('semester_id')
            ->orderBy('id')
            ->get();

        return $offerings->map(fn (CourseOffering $o): OfferingSummary => $this->toOfferingSummary($o))
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
            ->where('course_offering_id', $offeringId)
            ->whereIn('course_status', self::gradableStatuses())
            ->orderBy('id')
            ->get();

        return $enrollments->map(fn (Enrollment $e): EnrollmentSummary => new EnrollmentSummary(
            id: (int) $e->getKey(),
            studentId: (int) $e->student_id,
            courseOfferingId: (int) $e->course_offering_id,
            classification: $e->classification->value,
            courseStatus: $e->course_status->value,
            creditHours: (int) $e->credit_hours,
        ))->values()->all();
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
