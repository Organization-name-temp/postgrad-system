<?php

namespace Modules\Records\Internal\Support;

use Modules\Calendar\Public\TranscriptReadGuard as CalendarGuard;
use Modules\Catalog\Public\TranscriptReadGuard as CatalogGuard;
use Modules\Enrollment\Public\TranscriptReadGuard as EnrollmentGuard;
use Modules\Grading\Public\TranscriptReadGuard as GradingGuard;
use Modules\Identity\Public\TranscriptReadGuard as IdentityGuard;

final readonly class TranscriptSources
{
    public function __construct(private GradingGuard $grading, private EnrollmentGuard $enrollment,
        private CatalogGuard $catalog, private CalendarGuard $calendar, private IdentityGuard $identity) {}

    public function lock(): void
    {
        $this->grading->lock();
        $this->enrollment->lock();
        $this->catalog->lock();
        $this->calendar->lock();
        $this->identity->lock();
    }
}
