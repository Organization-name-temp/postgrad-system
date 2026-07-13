import { describe, expect, it } from 'vitest'
import { activeStatusChip } from './statusChips'

const labels = { active: 'Active', inactive: 'Inactive' }

describe('activeStatusChip', () => {
  it('maps an active entity to a success chip with the active label', () => {
    const chip = activeStatusChip(true, labels)
    expect(chip).toMatchObject({ tone: 'success', label: 'Active' })
    expect(chip.icon).toBeDefined()
  })

  it('maps an inactive entity to a neutral chip with the inactive label', () => {
    const chip = activeStatusChip(false, labels)
    expect(chip).toMatchObject({ tone: 'neutral', label: 'Inactive' })
    expect(chip.icon).toBeDefined()
  })

  it('always carries a text label so colour is not the only signal', () => {
    expect(activeStatusChip(true, labels).label).toBe('Active')
    expect(activeStatusChip(false, labels).label).toBe('Inactive')
  })
})
