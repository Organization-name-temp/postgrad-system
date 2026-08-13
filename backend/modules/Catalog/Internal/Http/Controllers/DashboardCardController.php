<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;

/**
 * @group Catalog
 *
 * Catalog's Dashboard Card (phase 4.5, ADR-0024) — the second of the two Cards about how the
 * system is **configured** rather than about a queue of work. Read-only, living in the module that
 * owns the data, independently authorized by its own route gate, and returning typed domain
 * values — here two counts — rather than pre-rendered display strings.
 *
 * A Card is not a Report: this answers "is the catalog populated?" at a glance and is fixed and
 * summary, where Phase 7's Enrollment Summary report is filterable and parameterised. The two are
 * different objects (spec §Glossary), so Phase 7 does not need to re-litigate it.
 */
class DashboardCardController
{
    /**
     * Catalog Summary Card.
     *
     * How many Programs and Courses are **active** — the answer to "is the catalog populated?"
     * without a navigation, linking into the catalog itself. Both are aggregate `COUNT`s, never a
     * materialised collection counted in PHP, so the Card costs two scalar queries however large
     * the catalog grows. A catalog with nothing active returns zeros and the SPA renders the
     * shared empty state rather than a bare `0`.
     *
     * Gated on `catalog.manage` (§3.2 — Department Head / Dean; administrators inherit it).
     *
     * @response 200 {"data": {"active_program_count": 6, "active_course_count": 42}}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function catalogSummary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'active_program_count' => Program::query()->where('is_active', true)->count(),
                'active_course_count' => Course::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
