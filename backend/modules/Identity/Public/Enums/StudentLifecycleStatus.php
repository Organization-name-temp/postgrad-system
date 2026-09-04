<?php

namespace Modules\Identity\Public\Enums;

/** Operational eligibility of the Identity-owned Student anchor (ADR-0040). */
enum StudentLifecycleStatus: string
{
    case Active = 'Active';
    case Terminated = 'Terminated';
}
