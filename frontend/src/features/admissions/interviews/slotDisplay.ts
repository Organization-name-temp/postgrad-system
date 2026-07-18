/**
 * Pure formatting helpers for interview slots, shared across the staff, interviewer,
 * and applicant interview screens (ticket 09). Kept free of React/i18n so the
 * where-is-it and when-is-it rules have one tested home.
 *
 * Times are rendered in UTC wall-clock (the app timezone — ADR-0005 localhost build),
 * matching the Admission Cycle window display, so everyone sees the one canonical time.
 */

/** The subset of a slot these helpers read — kept structural so every generated
 *  slot shape (SA list, applicant view, interviewer queue) satisfies it. */
export interface SlotLike {
  mode?: string
  location?: string | null
  meeting_link?: string | null
  scheduled_at?: string
  duration_minutes?: number
}

/**
 * Where the interview happens: the physical location for an in-person slot, or the
 * meeting link for a virtual one. Returns null when the relevant coordinate is absent,
 * so callers can fall back to a dash.
 */
export function slotWhere(slot: SlotLike): string | null {
  if (slot.mode === 'virtual') {
    return slot.meeting_link ?? null
  }
  return slot.location ?? null
}

/**
 * When the interview happens, as a locale-aware date+time in UTC. Returns an empty
 * string for a missing/unparseable timestamp so the field stays blank.
 */
export function formatSlotWhen(iso: string | undefined, locale: string): string {
  if (!iso) {
    return ''
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'UTC',
  }).format(date)
}
