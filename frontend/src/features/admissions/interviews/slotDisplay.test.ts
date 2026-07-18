import { describe, expect, it } from 'vitest'
import { formatSlotWhen, slotWhere } from './slotDisplay'

describe('slotWhere', () => {
  it('returns the physical location for an in-person slot', () => {
    expect(slotWhere({ mode: 'in_person', location: 'Room 204', meeting_link: null })).toBe(
      'Room 204',
    )
  })

  it('returns the meeting link for a virtual slot', () => {
    expect(
      slotWhere({ mode: 'virtual', location: null, meeting_link: 'https://meet.ejust/x' }),
    ).toBe('https://meet.ejust/x')
  })

  it('returns null when the relevant coordinate is missing', () => {
    expect(slotWhere({ mode: 'in_person', location: null })).toBeNull()
    expect(slotWhere({ mode: 'virtual', meeting_link: null })).toBeNull()
  })
})

describe('formatSlotWhen', () => {
  it('formats a timestamp in UTC wall-clock', () => {
    // 10:00 UTC must render as 10:00 regardless of the runner's local timezone.
    const formatted = formatSlotWhen('2026-07-20T10:00:00+00:00', 'en-GB')
    expect(formatted).toContain('10:00')
    expect(formatted).toMatch(/2026/)
  })

  it('returns an empty string for missing or unparseable input', () => {
    expect(formatSlotWhen(undefined, 'en')).toBe('')
    expect(formatSlotWhen('not-a-date', 'en')).toBe('')
  })
})
