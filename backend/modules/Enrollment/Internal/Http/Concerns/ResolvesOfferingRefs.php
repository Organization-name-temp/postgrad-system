<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Identity\Public\Contracts\Directory;

/**
 * Resolve a set of Course Offerings' cross-module references — the Catalog Course each
 * teaches and the Identity Instructor each is assigned — in one read per facade, and
 * stash the resulting DTOs on each Offering as `resolved_course` / `resolved_instructor`
 * (ADR-0012, mirroring Admissions' ResolvesInterviewers concern). The offerings
 * resource renders from those stashes, so listing a semester's offerings costs two
 * facade reads, not two per row. References are opaque ids (no FK); a dropped
 * reference stashes null.
 */
trait ResolvesOfferingRefs
{
    /**
     * @param  Collection<int, CourseOffering>  $offerings
     */
    protected function attachOfferingRefs(
        Collection $offerings,
        CourseCatalog $courses,
        Directory $directory,
    ): void {
        $courseIds = $offerings->pluck('course_id')->filter()->unique()->values()->all();
        $instructorIds = $offerings->pluck('instructor_id')->filter()->unique()->values()->all();

        $courseSummaries = $courseIds === [] ? [] : $courses->findMany($courseIds);
        $instructorSummaries = $instructorIds === [] ? [] : $directory->findMany($instructorIds);

        $offerings->each(function (CourseOffering $offering) use ($courseSummaries, $instructorSummaries): void {
            $offering->setAttribute('resolved_course', $courseSummaries[$offering->course_id] ?? null);
            $offering->setAttribute('resolved_instructor', $instructorSummaries[$offering->instructor_id] ?? null);
        });
    }
}
