<?php

return [
    'minimum_cgpa' => 3.0,
    'probation_credit_hour_ceiling' => (int) env('ACADEMIC_STANDING_PROBATION_CH_CEILING', 6),
];
