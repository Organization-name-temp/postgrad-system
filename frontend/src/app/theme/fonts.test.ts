import { beforeEach, describe, expect, it } from 'vitest'
import { preloadHotFonts } from './fonts'

const preloadLinks = () =>
  Array.from(document.head.querySelectorAll('link[rel="preload"][as="font"]'))

describe('preloadHotFonts', () => {
  beforeEach(() => {
    document.head.querySelectorAll('link[rel="preload"]').forEach((l) => l.remove())
  })

  it('preloads two woff2 weights of the Latin family for English, CORS-enabled', () => {
    preloadHotFonts('en')

    const links = preloadLinks()
    expect(links).toHaveLength(2)
    expect(links.every((l) => l.getAttribute('type') === 'font/woff2')).toBe(true)
    expect(links.every((l) => l.hasAttribute('crossorigin'))).toBe(true)
    expect(links.some((l) => /ibm-plex-sans-latin/.test(l.getAttribute('href') ?? ''))).toBe(true)
  })

  it('preloads the Arabic family for Arabic locales, including region subtags', () => {
    preloadHotFonts('ar-EG')

    const links = preloadLinks()
    expect(links).toHaveLength(2)
    expect(links.some((l) => /ibm-plex-sans-arabic/.test(l.getAttribute('href') ?? ''))).toBe(true)
  })

  it('is idempotent — repeated calls do not duplicate links', () => {
    preloadHotFonts('en')
    preloadHotFonts('en')

    expect(preloadLinks()).toHaveLength(2)
  })

  it('keeps only the active language on switch — stale preloads are removed', () => {
    preloadHotFonts('en')
    preloadHotFonts('ar')

    const links = preloadLinks()
    expect(links).toHaveLength(2)
    expect(links.every((l) => /ibm-plex-sans-arabic/.test(l.getAttribute('href') ?? ''))).toBe(true)
  })
})
