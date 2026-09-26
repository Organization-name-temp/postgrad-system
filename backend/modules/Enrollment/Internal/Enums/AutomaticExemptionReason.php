<?php

namespace Modules\Enrollment\Internal\Enums;

enum AutomaticExemptionReason: string
{
    case EnglishProficiency = 'english_proficiency';
    case SameDepartmentEjustMsc = 'same_department_ejust_msc';
    case EjustBsc = 'ejust_bsc';
}
