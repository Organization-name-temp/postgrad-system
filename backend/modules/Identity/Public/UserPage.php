<?php

namespace Modules\Identity\Public;

/** Bounded directory summaries and selection totals; no persistence models cross the seam. */
final readonly class UserPage
{
    /** @param list<UserSummary> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage) {}
}
