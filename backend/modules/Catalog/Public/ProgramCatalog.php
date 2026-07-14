<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\CatalogServiceProvider;

/**
 * The read facade other modules use to resolve Catalog Programs by id (ADR-0012).
 * It is the sanctioned Catalog\Public seam: callers store a Program's id as an
 * opaque reference (no cross-module foreign key) and read its details back only
 * through this contract, which returns {@see ProgramSummary} DTOs — never Catalog's
 * Eloquent models. The concrete implementation lives in `Catalog\Internal` and is
 * bound in {@see CatalogServiceProvider}.
 *
 * Admissions is the first consumer (program selection + qualification, ticket 06);
 * the Phase-3 elective-classification reads extend this same facade.
 */
interface ProgramCatalog
{
    /**
     * The summary of the Program with the given id, or `null` when no such Program
     * exists. Callers must handle `null`: with no database foreign key backing the
     * reference, an id can dangle (in practice Catalog is deactivate-only, so a
     * Program is never hard-deleted — BR-18), and the caller validates existence.
     */
    public function find(int $id): ?ProgramSummary;

    /**
     * The summaries for the given ids, keyed by id; ids with no matching Program are
     * simply absent from the result. Lets a screen resolve several references
     * (an applicant's primary + alternative, or a reviewer's queue) in one read
     * instead of N calls to {@see self::find()}.
     *
     * @param  list<int>  $ids
     * @return array<int, ProgramSummary>
     */
    public function findMany(array $ids): array;
}
