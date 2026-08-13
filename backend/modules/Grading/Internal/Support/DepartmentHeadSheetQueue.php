<?php

namespace Modules\Grading\Internal\Support;

use Illuminate\Support\Collection;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Http\Concerns\ScopesToDepartmentHead;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;

/**
 * "Which Submitted Grade Sheets is this Department Head the reviewer of?" — extracted here (phase
 * 4.5, ticket 05) so the review queue screen and the Sheets Awaiting Review Dashboard Card read
 * the **same** answer rather than two implementations of it. A Card must count exactly what the
 * queue it links into would show, including the case that is easy to get wrong.
 *
 * That case is the nullable Department scope: because `Course.department_id` is nullable (ADR-0010
 * — the shared Preparatory courses), a Head assigned **university-wide** reviews the
 * department-less Preparatory Sheets and a Department-scoped Head does not. It needs no branch
 * here: the routing is `headsForDepartment($course->departmentId)` with null passed straight
 * through, exactly as {@see ScopesToDepartmentHead} resolves it from the other direction. A
 * Department with no Head assigned simply matches nobody, so its Sheets appear on no queue and no
 * Card — and that is not an error.
 *
 * The reads are batched for the whole queue rather than one per Sheet: the Offerings through
 * `Enrollment\Public`, the Courses through `Catalog\Public` (ADR-0012), and one
 * `headsForDepartment()` call per distinct Department — usually a single call, since a Head
 * typically covers one Department.
 */
final readonly class DepartmentHeadSheetQueue
{
    public function __construct(
        private EnrollmentRecords $enrollments,
        private CourseCatalog $courses,
        private DepartmentHeadAssignments $heads,
    ) {}

    /**
     * The Submitted Sheets routed to `$userId` as a Department Head, oldest signature first,
     * alongside the Offering and Course each was resolved through — so a caller renders rows
     * without a second round of facade reads. Empty for a user who heads no Department.
     *
     * @return array{
     *     sheets: Collection<int, GradeSheet>,
     *     offerings: array<int, OfferingSummary>,
     *     courses: array<int, CourseSummary>,
     * }
     */
    public function forHead(int $userId): array
    {
        $sheets = GradeSheet::query()
            ->where('status', GradeSheetStatus::Submitted)
            ->orderBy('signed_at')
            ->orderBy('id')
            ->get();

        if ($sheets->isEmpty()) {
            return ['sheets' => $sheets, 'offerings' => [], 'courses' => []];
        }

        $offerings = [];
        foreach ($sheets->pluck('course_offering_id')->unique() as $offeringId) {
            $offering = $this->enrollments->findOffering((int) $offeringId);
            if ($offering !== null) {
                $offerings[(int) $offeringId] = $offering;
            }
        }

        $courses = $this->courses->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->courseId,
            $offerings,
        ))));

        // One headsForDepartment() call per distinct Department across the whole queue, not per
        // Sheet. The -1 cache key stands in for the null (university-wide) scope, which is a
        // scope and not a missing argument.
        $headsCache = [];
        $mine = $sheets->filter(function (GradeSheet $sheet) use (&$headsCache, $offerings, $courses, $userId): bool {
            $offering = $offerings[(int) $sheet->course_offering_id] ?? null;
            $course = $offering === null ? null : ($courses[$offering->courseId] ?? null);
            if ($course === null) {
                return false;
            }

            $cacheKey = $course->departmentId ?? -1;
            if (! array_key_exists($cacheKey, $headsCache)) {
                $headsCache[$cacheKey] = $this->heads->headsForDepartment($course->departmentId);
            }

            return in_array($userId, $headsCache[$cacheKey], true);
        })->values();

        return ['sheets' => $mine, 'offerings' => $offerings, 'courses' => $courses];
    }
}
