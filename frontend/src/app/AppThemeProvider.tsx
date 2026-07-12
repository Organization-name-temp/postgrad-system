import { useMemo } from 'react'
import type { ReactNode } from 'react'
import { CacheProvider } from '@emotion/react'
import createCache from '@emotion/cache'
import { CssBaseline, ThemeProvider } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { prefixer } from 'stylis'
import rtlPlugin from 'stylis-plugin-rtl'
import { isRtlLocale } from '../shared/i18n/direction'
import { createAppTheme } from './theme/createAppTheme'

// Two Emotion caches: the RTL one runs the stylis RTL plugin so physical CSS
// (margins, paddings, positioning) mirrors for Arabic. Swapping the cache +
// recreating the theme on language change flips direction/font with no reload.
const ltrCache = createCache({ key: 'mui' })
const rtlCache = createCache({ key: 'muirtl', stylisPlugins: [prefixer, rtlPlugin] })

/**
 * Two independent theming axes (docs/design-system.md §3.5):
 *   - Light ⇄ dark is a CSS-variable attribute flip via `useColorScheme`
 *     (`data-mui-color-scheme`), so toggling recreates nothing and re-renders
 *     nothing. `defaultMode="system"` means a first visit follows the OS; a
 *     pre-paint script in index.html sets the attribute before first paint so
 *     there's no flash. The choice persists in localStorage (`mui-mode`).
 *   - Direction + font recreate the theme + swap the Emotion cache (below),
 *     memoised on language only. The dark selection survives a language switch
 *     because it lives in the DOM attribute + localStorage, not the theme.
 */
export function AppThemeProvider({ children }: { children: ReactNode }) {
  const { i18n } = useTranslation()
  const isRtl = isRtlLocale(i18n.language)
  const theme = useMemo(() => createAppTheme(i18n.language), [i18n.language])

  return (
    <CacheProvider value={isRtl ? rtlCache : ltrCache}>
      <ThemeProvider theme={theme} defaultMode="system">
        <CssBaseline enableColorScheme />
        {children}
      </ThemeProvider>
    </CacheProvider>
  )
}
