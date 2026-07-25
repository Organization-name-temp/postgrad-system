<?php

namespace Modules\Enrollment\Public;

use Modules\Enrollment\Internal\Models\CourseOffering;

/**
 * A read-only view of a Course Offering for consumers outside Enrollment (ADR-0012). This is
 * the *only* shape in which Offering data crosses the module seam: the Eloquent
 * {@see CourseOffering} model never leaves `Enrollment\Internal`, so a consumer gets exactly
 * these four ids and none of Enrollment's write surface.
 *
 * Every field but the Offering's own id is an **opaque reference** the caller resolves through
 * the owning module's facade — the Semester through `Calendar\Public`, the Course through
 * `Catalog\Public`, the Instructor through `Identity\Public`. Nothing display-related is
 * carried here on purpose: these are the routing and scoping facts (who teaches it, which
 * Course, which Semester), which is precisely what Grading needs to authorise a Sheet and
 * route it to a Department Head, and no more.
 *
 * An immutable value object — the facade builds it, callers only read it.
 */
final readonly class OfferingSummary
{
    public function __construct(
        public int $id,
        public int $semesterId,
        public int $courseId,
        public int $instructorId,
    ) {}
}
