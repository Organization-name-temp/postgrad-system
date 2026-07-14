import { describe, expect, it } from 'vitest'
import { cycleState, cycleStatusChip } from './cycleStatus'

const labels = { open: 'Open', upcoming: 'Upcoming', closed: 'Closed', inactive: 'Inactive' }
const now = new Date('2026-07-01T00:00:00Z')

describe('cycleState', () => {
  it('is inactive when the cycle is deactivated, regardless of window', () => {
    expect(
      cycleState({ is_active: false, is_open: false, opens_at: '2026-06-01T00:00:00Z' }, now),
    ).toBe('inactive')
  })

  it('is open when the server reports it open', () => {
    expect(cycleState({ is_active: true, is_open: true }, now)).toBe('open')
  })

  it('is upcoming when active but its window has not started yet', () => {
    expect(
      cycleState({ is_active: true, is_open: false, opens_at: '2026-08-01T00:00:00Z' }, now),
    ).toBe('upcoming')
  })

  it('is closed when active but its window has already elapsed', () => {
    expect(
      cycleState({ is_active: true, is_open: false, opens_at: '2026-05-01T00:00:00Z' }, now),
    ).toBe('closed')
  })
})

describe('cycleStatusChip', () => {
  it('maps each state to a distinct tone and always carries a label and icon', () => {
    const open = cycleStatusChip('open', labels)
    expect(open).toMatchObject({ tone: 'success', label: 'Open' })
    expect(open.icon).toBeDefined()

    const upcoming = cycleStatusChip('upcoming', labels)
    expect(upcoming).toMatchObject({ tone: 'info', label: 'Upcoming' })
    expect(upcoming.icon).toBeDefined()

    const closed = cycleStatusChip('closed', labels)
    expect(closed).toMatchObject({ tone: 'neutral', label: 'Closed' })
    expect(closed.icon).toBeDefined()

    const inactive = cycleStatusChip('inactive', labels)
    expect(inactive).toMatchObject({ tone: 'neutral', label: 'Inactive' })
    expect(inactive.icon).toBeDefined()
  })
})
