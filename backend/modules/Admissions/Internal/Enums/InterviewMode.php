<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * How an interview is held — in person on campus, or virtually over a meeting link
 * (ticket 09). Chosen by Student Affairs when they create an {@see InterviewSlot};
 * it decides which of a slot's two optional coordinates is meaningful — `location`
 * for {@see self::InPerson}, `meeting_link` for {@see self::Virtual}. A closed set
 * that lives in code, in the same style as the Admissions IntakeTerm and Catalog
 * DegreeLevel enums (ADR-0008); Admissions-owned and Internal.
 */
enum InterviewMode: string
{
    case InPerson = 'in_person';
    case Virtual = 'virtual';
}
