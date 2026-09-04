<?php

namespace Modules\Enrollment\Internal\Enums;

enum ExemptionRequestStatus: string
{
    case Draft = 'Draft';
    case PendingAdvisor = 'PendingAdvisor';
    case PendingDepartmentHead = 'PendingDepartmentHead';
    case Returned = 'Returned';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Abandoned = 'Abandoned';

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Abandoned], true);
    }
}
