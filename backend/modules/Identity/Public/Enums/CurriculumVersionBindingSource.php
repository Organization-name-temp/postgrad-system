<?php

namespace Modules\Identity\Public\Enums;

/** How Identity selected the immutable Curriculum Version governing a Student. */
enum CurriculumVersionBindingSource: string
{
    case AdmissionTime = 'admission_time';
    case EarliestAvailableFallback = 'earliest_available_fallback';
}
