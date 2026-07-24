// Pure client-side helpers for an Offering's weekly meeting slots. The server is the
// authority on the meeting invariants (ticket 03); these mirror its interval-overlap
// rule (ADR-0014) so the form can flag a bad schedule before submitting and format a
// slot for display. No date library — meetings are naive "HH:MM" wall-clock times.

/** The seven weekday values (0 = Sunday … 6 = Saturday), matching the backend enum. */
export const WEEKDAY_VALUES = [0, 1, 2, 3, 4, 5, 6] as const

/** A meeting slot as edited in the form (weekday/time may be blank while drafting). */
export interface MeetingDraft {
  weekday: string
  starts_at: string
  ends_at: string
}

/** Minutes from midnight for an "HH:MM" string; NaN for a blank/invalid value. */
function toMinutes(clock: string): number {
  const match = /^(\d{1,2}):(\d{2})$/.exec(clock)
  if (!match) {
    return Number.NaN
  }
  return Number(match[1]) * 60 + Number(match[2])
}

/** Whether a draft row is filled in and ends strictly after it starts. */
export function isMeetingComplete(meeting: MeetingDraft): boolean {
  if (meeting.weekday === '' || meeting.starts_at === '' || meeting.ends_at === '') {
    return false
  }
  const start = toMinutes(meeting.starts_at)
  const end = toMinutes(meeting.ends_at)
  return !Number.isNaN(start) && !Number.isNaN(end) && end > start
}

/**
 * Whether two meetings clash — same weekday and intersecting half-open [start, end)
 * intervals (the mirror of the server's MeetingTime::overlaps). Touching endpoints do
 * not clash.
 */
export function meetingsOverlap(a: MeetingDraft, b: MeetingDraft): boolean {
  if (a.weekday !== b.weekday) {
    return false
  }
  const aStart = toMinutes(a.starts_at)
  const aEnd = toMinutes(a.ends_at)
  const bStart = toMinutes(b.starts_at)
  const bEnd = toMinutes(b.ends_at)
  if ([aStart, aEnd, bStart, bEnd].some(Number.isNaN)) {
    return false
  }
  return aStart < bEnd && bStart < aEnd
}

/** Whether any two of the (complete) meetings clash — the self-overlap the server rejects. */
export function hasSelfOverlap(meetings: MeetingDraft[]): boolean {
  const usable = meetings.filter(isMeetingComplete)
  for (let i = 0; i < usable.length; i++) {
    for (let j = i + 1; j < usable.length; j++) {
      if (meetingsOverlap(usable[i], usable[j])) {
        return true
      }
    }
  }
  return false
}

/** "09:00 – 11:00" for read-only display. */
export function formatMeetingRange(
  startsAt: string | undefined,
  endsAt: string | undefined,
): string {
  return `${startsAt ?? ''} – ${endsAt ?? ''}`
}
