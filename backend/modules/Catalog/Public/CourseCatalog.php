<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\CatalogServiceProvider;

/**
 * The read facade other modules use to resolve Catalog Courses by id (ADR-0012) —
 * the long-deferred Course-reader sibling of {@see ProgramCatalog} (ADR-0010's
 * consequence note; ADR-0012). It is the sanctioned Catalog\Public seam: callers
 * store a Course's id as an opaque reference (no cross-module foreign key) and read
 * its details back only through this contract, which returns {@see CourseSummary}
 * DTOs — never Catalog's Eloquent models. The concrete implementation lives in
 * `Catalog\Internal` and is bound in {@see CatalogServiceProvider}.
 *
 * Enrollment is the first consumer (ticket 03): a Course Offering resolves the Course
 * it teaches — for the offerings list, the seat/schedule screens, and (later tickets)
 * eligibility classification and the CH snapshot minted onto an Enrollment (ADR-0017).
 */
interface CourseCatalog
{
    /**
     * The summary of the Course with the given id, or `null` when no such Course
     * exists. Callers must handle `null`: with no database foreign key backing the
     * reference, an id can dangle (in practice Catalog is deactivate-only, so a Course
     * is never hard-deleted — BR-18), and the caller validates existence.
     */
    public function find(int $id): ?CourseSummary;

    /**
     * The summaries for the given ids, keyed by id; ids with no matching Course are
     * simply absent from the result. Lets a screen resolve several references (every
     * Course taught in a Semester) in one read instead of N calls to {@see self::find()}.
     *
     * @param  list<int>  $ids
     * @return array<int, CourseSummary>
     */
    public function findMany(array $ids): array;
}
