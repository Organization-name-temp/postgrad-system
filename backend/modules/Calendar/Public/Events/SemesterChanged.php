<?php

namespace Modules\Calendar\Public\Events;

/** ID-only signal; consumers must reread the authoritative Semester DTO. */
final readonly class SemesterChanged
{
    public function __construct(public int $semesterId) {}
}
