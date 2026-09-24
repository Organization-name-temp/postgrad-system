<?php

namespace Modules\AcademicStanding\Public;

enum GraduationBlocker: string
{
    case RemainingRequirements = 'RemainingRequirements';
    case CgpaUnavailable = 'CgpaUnavailable';
    case CgpaBelowMinimum = 'CgpaBelowMinimum';
    case TerminationPending = 'TerminationPending';
}
