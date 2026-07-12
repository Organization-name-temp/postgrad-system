import { describe, expect, it } from 'vitest'
import { contrastRatio, meetsAA } from '../../shared/a11y/contrast'
import { primitive, semantic } from './tokens'

// The load-bearing WCAG AA gate for the design tokens (design-system.md §12).
// Every pair that renders readable text or an essential UI indicator must clear
// AA in BOTH colour schemes. Ratios were measured with the WebAIM-verified util
// in shared/a11y/contrast.

type Pair = { name: string; fg: string; bg: string }

const L = semantic.light
const D = semantic.dark

// Normal text: >= 4.5:1
const lightText: Pair[] = [
  { name: 'text.primary on default', fg: L.text.primary, bg: L.background.default },
  { name: 'text.primary on paper', fg: L.text.primary, bg: L.background.paper },
  { name: 'text.secondary on default', fg: L.text.secondary, bg: L.background.default },
  { name: 'text.secondary on paper', fg: L.text.secondary, bg: L.background.paper },
  { name: 'button label on primary', fg: L.primary.contrastText, bg: L.primary.main },
  { name: 'brand-red text on paper', fg: L.primary.text, bg: L.background.paper },
  { name: 'secondary text on paper', fg: L.secondary.main, bg: L.background.paper },
  { name: 'success text on paper', fg: L.success.dark, bg: L.background.paper },
  { name: 'warning text on paper', fg: L.warning.dark, bg: L.background.paper },
  { name: 'error text on paper', fg: L.error.dark, bg: L.background.paper },
  { name: 'info text on paper', fg: L.info.dark, bg: L.background.paper },
]
const darkText: Pair[] = [
  { name: 'text.primary on default', fg: D.text.primary, bg: D.background.default },
  { name: 'text.primary on paper', fg: D.text.primary, bg: D.background.paper },
  { name: 'text.secondary on paper', fg: D.text.secondary, bg: D.background.paper },
  { name: 'button label on primary', fg: D.primary.contrastText, bg: D.primary.main },
  { name: 'brand-red text on paper', fg: D.primary.text, bg: D.background.paper },
  { name: 'secondary text on paper', fg: D.secondary.main, bg: D.background.paper },
  { name: 'success text on paper', fg: D.success.main, bg: D.background.paper },
  { name: 'warning text on paper', fg: D.warning.main, bg: D.background.paper },
  { name: 'error text on paper', fg: D.error.main, bg: D.background.paper },
  { name: 'info text on paper', fg: D.info.main, bg: D.background.paper },
]

// UI indicators / fills: >= 3:1
const lightUi: Pair[] = [
  { name: 'focus ring on default', fg: L.focusRing, bg: L.background.default },
  { name: 'focus ring on paper', fg: L.focusRing, bg: L.background.paper },
  { name: 'success fill on paper', fg: L.success.main, bg: L.background.paper },
  { name: 'warning fill on paper', fg: L.warning.main, bg: L.background.paper },
  { name: 'error fill on paper', fg: L.error.main, bg: L.background.paper },
  { name: 'info fill on paper', fg: L.info.main, bg: L.background.paper },
]
const darkUi: Pair[] = [{ name: 'focus ring on paper', fg: D.focusRing, bg: D.background.paper }]

describe('token contrast — WCAG AA', () => {
  it.each(lightText)('light normal text: $name', ({ fg, bg }) => {
    expect(meetsAA(fg, bg)).toBe(true)
  })
  it.each(darkText)('dark normal text: $name', ({ fg, bg }) => {
    expect(meetsAA(fg, bg)).toBe(true)
  })
  it.each(lightUi)('light UI/large: $name', ({ fg, bg }) => {
    expect(meetsAA(fg, bg, { large: true })).toBe(true)
  })
  it.each(darkUi)('dark UI/large: $name', ({ fg, bg }) => {
    expect(meetsAA(fg, bg, { large: true })).toBe(true)
  })

  it('uses #B00020 for red text because it clears AA with more headroom than the vivid brand red', () => {
    const vivid = contrastRatio(primitive.brand[500], primitive.ink[0]) // #E4002B on white
    const text = contrastRatio(primitive.brand[700], primitive.ink[0]) // #B00020 on white

    // NB: #E4002B actually passes AA on pure white (~4.85), contrary to a common
    // assumption — but only just, and it dips on tinted surfaces. #B00020 is the
    // body-text/link token for real headroom (~7.3), and #E4002B stays a
    // fills/accents/large colour (its comfortable >= 3:1 zone).
    expect(meetsAA(primitive.brand[700], primitive.ink[0])).toBe(true)
    expect(text).toBeGreaterThan(vivid)
    expect(vivid).toBeGreaterThanOrEqual(3)
  })

  it('has teeth — a too-light foreground is rejected', () => {
    expect(meetsAA(primitive.ink[300], primitive.ink[0])).toBe(false)
  })

  it('does not gate WCAG-exempt tokens (disabled text, decorative dividers)', () => {
    // Deliberately outside the gate, and asserted below-bar so the exemption is
    // visible, not an oversight:
    //  - disabled/inactive text — WCAG 1.4.3 exempts it.
    //  - the elevation divider — a decorative separator (§6 border-led elevation),
    //    never the sole cue for a boundary, so 1.4.11 does not require 3:1. The
    //    essential border-like indicator, the focus ring, IS gated above at 3:1;
    //    interactive/input border contrast is a component-level check for later.
    expect(meetsAA(L.text.disabled, L.background.paper)).toBe(false)
    expect(meetsAA(L.divider, L.background.paper, { large: true })).toBe(false)
  })
})
