import { describe, expect, it } from 'vitest'
import { semesterState, semesterStatusChip } from './semesterStatus'

const labels = {
  upcoming: 'Upcoming',
  registration: 'Registration open',
  running: 'Running',
  ended: 'Ended',
}
const now = new Date('2026-09-10T00:00:00Z')

// A well-ordered reference calendar the cases read against `now` (2026-09-10):
//   reg opens 2026-08-01 → reg closes 2026-09-05 → starts 2026-09-01 → ends 2026-12-20
const base = {
  registration_opens_at: '2026-08-01T00:00:00Z',
  registration_closes_at: '2026-09-05T00:00:00Z',
  starts_at: '2026-09-01T00:00:00Z',
  ends_at: '2026-12-20T00:00:00Z',
}

describe('semesterState', () => {
  it('is upcoming before registration opens', () => {
    expect(semesterState(base, new Date('2026-07-15T00:00:00Z'))).toBe('upcoming')
  })

  it('is registration inside the registration window before the term starts', () => {
    expect(semesterState(base, new Date('2026-08-15T00:00:00Z'))).toBe('registration')
  })

  it('is running once the term has started', () => {
    expect(semesterState(base, now)).toBe('running')
  })

  it('is ended after the semester end', () => {
    expect(semesterState(base, new Date('2026-12-25T00:00:00Z'))).toBe('ended')
  })

  it('is upcoming (not "registration open") once registration has closed but the term has not started', () => {
    // A term whose registration window closes before it starts: the gap must not
    // read as a stale "registration open" chip.
    const earlyClose = {
      registration_opens_at: '2026-08-01T00:00:00Z',
      registration_closes_at: '2026-08-20T00:00:00Z',
      starts_at: '2026-09-01T00:00:00Z',
      ends_at: '2026-12-20T00:00:00Z',
    }
    expect(semesterState(earlyClose, new Date('2026-08-25T00:00:00Z'))).toBe('upcoming')
  })
})

describe('semesterStatusChip', () => {
  it('maps each state to a distinct tone and always carries a label and icon', () => {
    const registration = semesterStatusChip('registration', labels)
    expect(registration).toMatchObject({ tone: 'success', label: 'Registration open' })
    expect(registration.icon).toBeDefined()

    const running = semesterStatusChip('running', labels)
    expect(running).toMatchObject({ tone: 'info', label: 'Running' })
    expect(running.icon).toBeDefined()

    const upcoming = semesterStatusChip('upcoming', labels)
    expect(upcoming).toMatchObject({ tone: 'secondary', label: 'Upcoming' })
    expect(upcoming.icon).toBeDefined()

    const ended = semesterStatusChip('ended', labels)
    expect(ended).toMatchObject({ tone: 'neutral', label: 'Ended' })
    expect(ended.icon).toBeDefined()
  })
})
