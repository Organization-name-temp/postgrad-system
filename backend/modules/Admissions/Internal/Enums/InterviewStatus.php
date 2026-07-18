<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The confirmation state of a booked {@see Interview} (ticket 09) — distinct from the
 * owning Application's {@see ApplicationStatus}, which stays `interview_scheduled`
 * for the whole "an interview is arranged" phase. A freshly scheduled interview is
 * {@see self::PendingConfirmation} until the applicant confirms it
 * ({@see self::Confirmed}); a reschedule returns it to `PendingConfirmation` for the
 * new slot. A closed, code-owned set (ADR-0008); Admissions-owned and Internal.
 */
enum InterviewStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
}
