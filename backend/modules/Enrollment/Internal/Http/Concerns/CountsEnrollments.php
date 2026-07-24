<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;

/**
 * Stash each Offering's active-Enrollment count as `enrolled_count`, read in one batched
 * query keyed by offering id (never a query per row), so {@see CourseOffering::seatsRemaining()}
 * reports honest seats wherever an Offering is shown or a cart is validated (ADR-0017,
 * mirroring {@see ResolvesOfferingRefs}). "Active" excludes a Withdrawal (W) — a withdrawn
 * Student leaves the live roster.
 */
trait CountsEnrollments
{
    /**
     * @param  Collection<int, CourseOffering>  $offerings
     */
    protected function attachEnrollmentCounts(Collection $offerings): void
    {
        $offeringIds = $offerings->pluck('id')->filter()->unique()->values()->all();

        $counts = $offeringIds === []
            ? []
            : DB::table('enrollments')
                ->select('course_offering_id', DB::raw('count(*) as aggregate'))
                ->whereIn('course_offering_id', $offeringIds)
                ->where('course_status', '!=', CourseStatus::Withdrawn->value)
                ->groupBy('course_offering_id')
                ->pluck('aggregate', 'course_offering_id')
                ->all();

        $offerings->each(fn (CourseOffering $offering) => $offering->setAttribute(
            'enrolled_count',
            (int) ($counts[$offering->getKey()] ?? 0),
        ));
    }
}
