<?php

namespace Modules\Enrollment\Public;

/** Owner-derived attendance, with null distinguishing an unmarked roster from 0%. */
final readonly class AttendanceSummary
{
    public function __construct(
        public int $enrollmentId,
        public int $present,
        public int $recorded,
        public ?float $percentage,
        public bool $atRisk,
    ) {}
}
