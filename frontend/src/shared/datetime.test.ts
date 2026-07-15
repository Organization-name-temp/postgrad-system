import { describe, expect, it } from 'vitest'
import { formatDate, isoToInputValue } from './datetime'

describe('isoToInputValue', () => {
  it('renders the UTC wall-clock so the datetime-local round-trip does not drift', () => {
    // A +00:00 timestamp maps to the same wall-clock the input shows, regardless
    // of the machine's local timezone (uses getUTC* internally).
    expect(isoToInputValue('2026-06-01T09:30:00+00:00')).toBe('2026-06-01T09:30')
  })

  it('normalises an offset timestamp back to its UTC wall-clock', () => {
    // 2026-06-01T11:30+02:00 is 09:30 UTC.
    expect(isoToInputValue('2026-06-01T11:30:00+02:00')).toBe('2026-06-01T09:30')
  })

  it('returns empty string for missing or unparseable input', () => {
    expect(isoToInputValue(undefined)).toBe('')
    expect(isoToInputValue('not-a-date')).toBe('')
  })
})

describe('formatDate', () => {
  it('formats in UTC so the shown date matches the stored one at day boundaries', () => {
    // 23:00 UTC must still read as June 1 (not shifted into June 2 in +TZ zones).
    expect(formatDate('2026-06-01T23:00:00+00:00', 'en-US')).toBe('Jun 1, 2026')
  })

  it('returns empty string for missing input', () => {
    expect(formatDate(undefined, 'en-US')).toBe('')
  })
})
