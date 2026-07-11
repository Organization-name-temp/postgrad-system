import { ToggleButton, ToggleButtonGroup } from '@mui/material'
import type { MouseEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../features/auth/useAuth'

/**
 * Flips the whole UI between English (LTR) and Arabic (RTL) at runtime and
 * persists the choice to the signed-in user's `preferred_locale`, so the next
 * session opens in the same language. Lives in `app/` because it composes the
 * shared i18n instance with the auth feature's persistence.
 */
export function LanguageSwitcher() {
  const { t, i18n } = useTranslation('common')
  const { persistLocale } = useAuth()

  const handleChange = (_event: MouseEvent<HTMLElement>, next: string | null) => {
    if (next && next !== i18n.language) {
      void i18n.changeLanguage(next)
      void persistLocale(next)
    }
  }

  return (
    <ToggleButtonGroup
      value={i18n.language}
      exclusive
      onChange={handleChange}
      size="small"
      aria-label={t('language.label')}
      color="standard"
      sx={{ '& .MuiToggleButton-root': { color: 'inherit', borderColor: 'currentColor' } }}
    >
      <ToggleButton value="en" aria-label={t('language.en')}>
        EN
      </ToggleButton>
      <ToggleButton value="ar" aria-label={t('language.ar')}>
        ع
      </ToggleButton>
    </ToggleButtonGroup>
  )
}
