import { describe, expect, it } from 'vitest'
import { createAppTheme } from './createAppTheme'
import { primitive } from './tokens'

// The design-system's core contract: these are load-bearing design decisions
// (ADR-0009 / design-system.md), not incidental internals — so they're worth
// pinning at the factory seam.
describe('createAppTheme', () => {
  it('enables CSS variables and ships both colour schemes in one theme', () => {
    const theme = createAppTheme('en')

    expect(theme.vars).toBeDefined() // cssVariables mode is on
    expect(theme.colorSchemes.light).toBeDefined()
    expect(theme.colorSchemes.dark).toBeDefined()
  })

  it('drives colour schemes off the data-mui-color-scheme attribute (not the OS media query)', () => {
    // The load-bearing wiring for a working manual toggle + the no-flash pre-paint
    // script: the selector must be the attribute, never 'media' or plain 'data'.
    expect(createAppTheme('en').colorSchemeSelector).toBe('data-mui-color-scheme')
  })

  it('uses the E-JUST brand red as the primary colour in both schemes', () => {
    const theme = createAppTheme('en')

    expect(theme.colorSchemes.light?.palette.primary.main.toLowerCase()).toBe(
      primitive.brand[500].toLowerCase(),
    )
    expect(theme.colorSchemes.dark?.palette.primary.main.toLowerCase()).toBe(
      primitive.brand[500].toLowerCase(),
    )
  })

  it('derives direction from the locale (RTL for Arabic, incl. region subtags)', () => {
    expect(createAppTheme('ar').direction).toBe('rtl')
    expect(createAppTheme('ar-EG').direction).toBe('rtl')
    expect(createAppTheme('en').direction).toBe('ltr')
  })

  it('never uppercases buttons', () => {
    expect(createAppTheme('en').typography.button.textTransform).toBe('none')
  })

  it('gives Arabic more leading than English at the same size', () => {
    const en = createAppTheme('en')
    const ar = createAppTheme('ar')

    expect(Number(ar.typography.h2.lineHeight)).toBeGreaterThan(Number(en.typography.h2.lineHeight))
  })
})
