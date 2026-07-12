import { describe, expect, it } from 'vitest'
import { AA_NORMAL, contrastRatio, meetsAA } from './contrast'

describe('contrastRatio', () => {
  it('matches known WCAG/WebAIM reference values', () => {
    // Black on white is the maximum, exactly 21:1.
    expect(contrastRatio('#000000', '#FFFFFF')).toBeCloseTo(21, 2)
    // Identical colours are the minimum, 1:1.
    expect(contrastRatio('#FFFFFF', '#FFFFFF')).toBeCloseTo(1, 5)
    // WebAIM's canonical "just passes AA" grey.
    expect(contrastRatio('#767676', '#FFFFFF')).toBeCloseTo(4.54, 1)
    // Pure red on white is the well-known ~4:1 that fails AA (4.5).
    expect(contrastRatio('#FF0000', '#FFFFFF')).toBeCloseTo(4.0, 1)
  })

  it('is order-independent', () => {
    expect(contrastRatio('#123456', '#abcdef')).toBeCloseTo(contrastRatio('#abcdef', '#123456'), 10)
  })

  it('supports 3-digit hex', () => {
    expect(contrastRatio('#000', '#fff')).toBeCloseTo(21, 2)
  })

  it('throws on malformed input rather than returning a silently wrong ratio', () => {
    expect(() => contrastRatio('not-a-colour', '#fff')).toThrow()
  })
})

describe('meetsAA', () => {
  it('applies the 4.5 normal / 3.0 large thresholds', () => {
    expect(AA_NORMAL).toBe(4.5)
    // #949494 on white ~ 3.03: passes large/UI, fails normal.
    expect(meetsAA('#949494', '#FFFFFF', { large: true })).toBe(true)
    expect(meetsAA('#949494', '#FFFFFF')).toBe(false)
  })

  it('has teeth — a clearly bad pairing fails', () => {
    expect(meetsAA('#CCCCCC', '#FFFFFF')).toBe(false)
  })
})
