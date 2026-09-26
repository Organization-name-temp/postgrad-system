<?php

namespace Modules\Enrollment\Internal\Enums;

enum CreditDecisionType: string
{
    case Exemption = 'exemption';
    case Transfer = 'transfer';
    case Waiver = 'waiver';
}
