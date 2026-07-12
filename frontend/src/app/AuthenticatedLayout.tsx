import { useEffect } from 'react'
import { AppBar, Box, Button, Container, Stack, Toolbar, Typography } from '@mui/material'
import { Outlet, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../features/auth/useAuth'
import { LanguageSwitcher } from './LanguageSwitcher'
import { ThemeModeToggle } from './ThemeModeToggle'

/**
 * The authenticated app shell: a top bar with the language switcher and log-out
 * over the routed page content. On mount it opens the UI in the user's saved
 * language, satisfying "the next login opens in the saved language".
 */
export function AuthenticatedLayout() {
  const navigate = useNavigate()
  const { t, i18n } = useTranslation('common')
  const { user, logout } = useAuth()

  const savedLocale = user?.preferred_locale
  useEffect(() => {
    if (savedLocale && savedLocale !== i18n.language) {
      void i18n.changeLanguage(savedLocale)
    }
  }, [savedLocale, i18n])

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <Box>
      <AppBar position="static">
        <Toolbar>
          <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
            {t('appName')}
          </Typography>
          <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
            <LanguageSwitcher />
            <ThemeModeToggle />
            <Button color="inherit" onClick={handleLogout}>
              {t('logOut')}
            </Button>
          </Stack>
        </Toolbar>
      </AppBar>
      <Container maxWidth="md" component="main" sx={{ py: 4 }}>
        <Outlet />
      </Container>
    </Box>
  )
}
