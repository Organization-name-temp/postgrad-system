<?php

namespace Modules\Catalog\Public\Events;

/** ID-only signal; consumers must reread the authoritative Course DTO. */
final readonly class CourseChanged
{
    public function __construct(public int $courseId) {}
}
