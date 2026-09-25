<?php

namespace Modules\Enrollment\Internal\Enums;

enum RemediationState: string
{
    case AwaitingOffering = 'AwaitingOffering';
    case Active = 'Active';
    case Satisfied = 'Satisfied';
    case Evaluated = 'Evaluated';
    case ReleasedByExemption = 'ReleasedByExemption';
}
