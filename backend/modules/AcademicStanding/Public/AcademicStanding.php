<?php

namespace Modules\AcademicStanding\Public;

enum AcademicStanding: string
{
    case TerminationPending = 'TerminationPending';
    case NotYetAssessed = 'NotYetAssessed';
    case AcademicProbation = 'AcademicProbation';
    case AcademicWarning = 'AcademicWarning';
    case GoodStanding = 'GoodStanding';

    public function severity(): int
    {
        return match ($this) {
            self::TerminationPending => 4,
            self::AcademicProbation => 3,
            self::AcademicWarning => 2,
            self::GoodStanding => 1,
            self::NotYetAssessed => 0,
        };
    }
}
