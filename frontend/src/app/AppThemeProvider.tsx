import { useMemo } from 'react'
import type { ReactNode } from 'react'
import { CacheProvider } from '@emotion/react'
import createCache from '@emotion/cache'
import { CssBaseline, ThemeProvider, createTheme } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { prefixer } from 'stylis'
import rtlPlugin from 'stylis-plugin-rtl'

// Two emotion caches: the RTL one runs the stylis RTL plugin so logical CSS
// (margins, paddings, positioning) mirrors for Arabic. Swapping the cache +
// theme direction on language change flips the whole UI with no reload.
const ltrCache = createCache({ key: 'mui' })
const rtlCache = createCache({ key: 'muirtl', stylisPlugins: [prefixer, rtlPlugin] })

export function AppThemeProvider({ children }: { children: ReactNode }) {
  const { i18n } = useTranslation()
  const isRtl = i18n.language === 'ar'
  const theme = useMemo(() => createTheme({ direction: isRtl ? 'rtl' : 'ltr' }), [isRtl])

  return (
    <CacheProvider value={isRtl ? rtlCache : ltrCache}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </ThemeProvider>
    </CacheProvider>
  )
}
