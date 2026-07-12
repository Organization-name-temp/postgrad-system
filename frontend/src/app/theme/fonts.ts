// Self-hosted type — no CDN. IBM Plex Sans (Latin) + IBM Plex Sans Arabic
// (Arabic), only the used subsets at the four shipped weights. @fontsource's CSS
// defaults to `font-display: swap`, so text shows in the fallback immediately
// (no invisible text). The theme picks the family by language (createAppTheme).
import '@fontsource/ibm-plex-sans/latin-400.css'
import '@fontsource/ibm-plex-sans/latin-500.css'
import '@fontsource/ibm-plex-sans/latin-600.css'
import '@fontsource/ibm-plex-sans/latin-700.css'
import '@fontsource/ibm-plex-sans-arabic/arabic-400.css'
import '@fontsource/ibm-plex-sans-arabic/arabic-500.css'
import '@fontsource/ibm-plex-sans-arabic/arabic-600.css'
import '@fontsource/ibm-plex-sans-arabic/arabic-700.css'

// The two hot weights per family (400 body + 600 heading), imported as URLs so
// Vite resolves them to the same hashed asset the @font-face above references.
import sansBody from '@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-400-normal.woff2?url'
import sansHeading from '@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-600-normal.woff2?url'
import arabicBody from '@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2?url'
import arabicHeading from '@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-600-normal.woff2?url'

import { isRtlLocale } from '../../shared/i18n/direction'

const HOT_WEIGHTS: Record<'en' | 'ar', readonly string[]> = {
  en: [sansBody, sansHeading],
  ar: [arabicBody, arabicHeading],
}

/**
 * Preload the active language's two hot font weights so first paint isn't held up
 * on them. Called at startup and again on every language switch; it keeps exactly
 * the active language's preloads — stale ones from the previous language are
 * removed, so we never accumulate both families. (Injected at runtime because the
 * hashed asset URL isn't known at index.html build time; the benefit is smaller
 * than a static <link> but still real.) Font preloads must be `crossorigin` even
 * same-origin, since fonts are always fetched in CORS mode — else a double-fetch.
 */
export function preloadHotFonts(lang: string): void {
  const family = isRtlLocale(lang) ? 'ar' : 'en'
  const wanted = new Set<string>(HOT_WEIGHTS[family])

  for (const link of document.head.querySelectorAll('link[rel="preload"][as="font"]')) {
    if (!wanted.has(link.getAttribute('href') ?? '')) link.remove()
  }

  for (const href of wanted) {
    if (document.head.querySelector(`link[rel="preload"][href="${href}"]`)) continue
    const link = document.createElement('link')
    // setAttribute (not the reflected props) so `as` is reliably queryable and
    // the markup is identical in jsdom and the browser.
    link.setAttribute('rel', 'preload')
    link.setAttribute('as', 'font')
    link.setAttribute('type', 'font/woff2')
    link.setAttribute('crossorigin', 'anonymous')
    link.setAttribute('href', href)
    document.head.appendChild(link)
  }
}

/**
 * Tabular (monospaced) figures so digits line up in columns. Spread onto numeric
 * cells — data grids, transcripts, GPA/credit-hour tables (later phases). Not
 * global: proportional figures are better for prose.
 */
export const tabularNums = { fontFeatureSettings: '"tnum" 1' } as const
