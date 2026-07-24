<?php

namespace Modules\Enrollment\Internal\Enums;

/**
 * The day of the week a Course Offering meets on. The integer values follow Carbon's
 * `dayOfWeek` numbering — Sunday 0 … Saturday 6 — so ticket 08's Class Session
 * generation can walk a Semester's calendar and match each date's weekday directly.
 * A closed set in code, in the same style as the Catalog and Identity enums (ADR-0008).
 *
 * Internal to Enrollment: the meeting weekday crosses to the SPA as its plain integer
 * value (the offerings resource), which the client maps to a bilingual label, so no
 * consumer references this type by name.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
}
