<?php

namespace Modules\Admissions\Public;

final readonly class ApplicationPipelineSummary
{
    /**
     * @param  array<string, int>  $statuses  current status counts, including zero statuses
     * @param  list<array{from_status: string, to_status: string, eligible: int, converted: int, rate: float|null}>  $conversions
     */
    public function __construct(public int $total, public array $statuses, public array $conversions) {}
}
