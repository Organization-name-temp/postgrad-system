import { IconButton } from '@mui/material'
import DarkModeIcon from '@mui/icons-material/DarkMode'
import LightModeIcon from '@mui/icons-material/LightMode'
import { useColorScheme } from '@mui/material/styles'
import { useTranslation } from 'react-i18next'

/**
 * Flips light ⇄ dark. Uses MUI's `useColorScheme`, so a click is a CSS-variable
 * attribute flip (no reload, no re-render) persisted to localStorage. Lives in
 * the app-bar chrome. The icon shows the mode you'd switch *to*.
 */
export function ThemeModeToggle() {
  const { t } = useTranslation('common')
  const { mode, systemMode, setMode } = useColorScheme()

  const resolved = mode === 'system' ? systemMode : mode
  const isDark = resolved === 'dark'

  // `mode` is undefined until the provider mounts — render the button disabled
  // (same size, no layout shift / hydration flip) until it resolves.
  return (
    <IconButton
      color="inherit"
      aria-label={t('theme.toggle')}
      title={t('theme.toggle')}
      disabled={!mode}
      onClick={mode ? () => setMode(isDark ? 'light' : 'dark') : undefined}
    >
      {isDark ? <LightModeIcon /> : <DarkModeIcon />}
    </IconButton>
  )
}
