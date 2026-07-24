import { describe, expect, it } from 'vitest'
import {
  formatMeetingRange,
  hasSelfOverlap,
  isMeetingComplete,
  meetingsOverlap,
  type MeetingDraft,
} from './meetingTime'

// The client mirror of the server's meeting invariants (ADR-0014) — the form uses
// these to flag a bad schedule before submitting; the API stays the authority.

const slot = (weekday: string, starts_at: string, ends_at: string): MeetingDraft => ({
  weekday,
  starts_at,
  ends_at,
})

describe('isMeetingComplete', () => {
  it('is complete when filled in and ending after it starts', () => {
    expect(isMeetingComplete(slot('1', '09:00', '11:00'))).toBe(true)
  })

  it('is incomplete with a blank field', () => {
    expect(isMeetingComplete(slot('', '09:00', '11:00'))).toBe(false)
    expect(isMeetingComplete(slot('1', '', '11:00'))).toBe(false)
    expect(isMeetingComplete(slot('1', '09:00', ''))).toBe(false)
  })

  it('is incomplete when it does not end after it starts', () => {
    expect(isMeetingComplete(slot('1', '11:00', '11:00'))).toBe(false)
    expect(isMeetingComplete(slot('1', '12:00', '11:00'))).toBe(false)
  })
})

describe('meetingsOverlap', () => {
  it('overlaps same-day intersecting intervals', () => {
    expect(meetingsOverlap(slot('1', '09:00', '11:00'), slot('1', '10:00', '12:00'))).toBe(true)
  })

  it('does not overlap different days', () => {
    expect(meetingsOverlap(slot('1', '09:00', '11:00'), slot('2', '09:00', '11:00'))).toBe(false)
  })

  it('does not overlap when they only touch at an endpoint', () => {
    expect(meetingsOverlap(slot('3', '09:00', '11:00'), slot('3', '11:00', '13:00'))).toBe(false)
  })
})

describe('hasSelfOverlap', () => {
  it('detects a clash within the set, ignoring incomplete rows', () => {
    expect(
      hasSelfOverlap([slot('1', '09:00', '11:00'), slot('1', '10:30', '12:00'), slot('', '', '')]),
    ).toBe(true)
  })

  it('is false when no two complete rows clash', () => {
    expect(
      hasSelfOverlap([
        slot('1', '09:00', '11:00'),
        slot('1', '11:00', '13:00'),
        slot('2', '09:00', '11:00'),
      ]),
    ).toBe(false)
  })
})

describe('formatMeetingRange', () => {
  it('renders a readable range', () => {
    expect(formatMeetingRange('09:00', '11:00')).toBe('09:00 – 11:00')
  })
})
