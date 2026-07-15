// Cross-cutting date helpers for the `datetime-local` form inputs and read-only
// timestamp columns. The API stores/returns timestamps in UTC (the app timezone —
// ADR-0005 localhost build), so both the value shown in an input and the read-only
// display are rendered in UTC wall-clock, and the naive value the input emits is sent
// back verbatim (the backend parses it as UTC). Everyone therefore sees the one
// canonical time. Shared because more than one feature edits UTC windows/times (the
// admission-cycle window, ticket 01; interview slots, ticket 09).

/**
 * Convert a server ISO-8601 timestamp into the value an
 * `<input type="datetime-local">` accepts — `YYYY-MM-DDTHH:mm` in UTC wall-clock.
 * Empty or unparseable input yields an empty string so the field stays blank.
 */
export function isoToInputValue(iso: string | undefined): string {
  if (!iso) {
    return ''
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  const pad = (value: number): string => String(value).padStart(2, '0')
  return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`
}

/**
 * Format an ISO-8601 timestamp as a locale-aware date (in UTC, to match the form)
 * for read-only display — e.g. the cycle window column. Returns an empty string for
 * missing input.
 */
export function formatDate(iso: string | undefined, locale: string): string {
  if (!iso) {
    return ''
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' }).format(date)
}
