import { useEffect, useState } from 'react'
import {
  AppBar,
  Box,
  Container,
  Drawer,
  IconButton,
  Link,
  Stack,
  Toolbar,
  Typography,
} from '@mui/material'
import MenuIcon from '@mui/icons-material/Menu'
import { Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../features/auth/useAuth'
import { LanguageSwitcher } from './LanguageSwitcher'
import { ThemeModeToggle } from './ThemeModeToggle'
import { UserMenu } from './UserMenu'
import { SideNav } from './SideNav'
import { AppBrand } from './AppBrand'
import { navItems } from './nav'

const DRAWER_WIDTH = 260

/**
 * The reusable authenticated shell every later phase drops into: a top app bar
 * (brand, page-title, language switcher, theme toggle, user menu) over a
 * role-aware side navigation — permanent on `lg+`, a drawer below. On mount it
 * opens the UI in the user's saved language.
 */
export function AuthenticatedLayout() {
  const { t, i18n } = useTranslation('common')
  const { user } = useAuth()
  const { pathname } = useLocation()
  const [mobileOpen, setMobileOpen] = useState(false)

  const savedLocale = user?.preferred_locale
  useEffect(() => {
    if (savedLocale && savedLocale !== i18n.language) {
      void i18n.changeLanguage(savedLocale)
    }
  }, [savedLocale, i18n])

  // Page title = the active nav item's label; falls back to the app name.
  const active = navItems.find((item) => item.to === pathname)
  const pageTitle = active ? t(`nav.${active.key}`) : t('appName')

  // Drawer anchor stays the default 'left' — MUI flips it to the right for RTL
  // automatically (via theme.direction), so flipping it here would double-flip.
  const nav = <SideNav items={navItems} onNavigate={() => setMobileOpen(false)} />

  return (
    <Box sx={{ display: 'flex' }}>
      {/* Keyboard skip link (WCAG 2.1, §12): hidden until focused, jumps past the
          nav to the content. Slides in from the top so it's direction-agnostic. */}
      <Link
        href="#main-content"
        sx={(theme) => ({
          position: 'fixed',
          insetInlineStart: theme.spacing(1),
          top: theme.spacing(1),
          zIndex: theme.zIndex.tooltip + 1,
          px: 2,
          py: 1,
          borderRadius: 1,
          bgcolor: 'background.paper',
          color: 'text.primary',
          boxShadow: 2,
          transform: 'translateY(-200%)',
          transition: 'transform 120ms',
          '&:focus': { transform: 'translateY(0)' },
        })}
      >
        {t('nav.skipToContent')}
      </Link>
      <AppBar
        position="fixed"
        elevation={0}
        sx={(theme) => ({
          zIndex: theme.zIndex.drawer + 1,
          bgcolor: 'background.paper',
          color: 'text.primary',
          borderBottom: '1px solid',
          borderColor: 'divider',
        })}
      >
        <Toolbar sx={{ gap: 1 }}>
          <IconButton
            color="inherit"
            edge="start"
            aria-label={t('nav.open')}
            onClick={() => setMobileOpen(true)}
            sx={{ display: { lg: 'none' } }}
          >
            <MenuIcon />
          </IconButton>
          <AppBrand />
          <Typography variant="subtitle1" component="div" noWrap color="text.secondary">
            {pageTitle}
          </Typography>
          <Box sx={{ flexGrow: 1 }} />
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
            <LanguageSwitcher />
            <ThemeModeToggle />
            <UserMenu />
          </Stack>
        </Toolbar>
      </AppBar>

      <Box sx={{ width: { lg: DRAWER_WIDTH }, flexShrink: { lg: 0 } }}>
        {/* Below lg: a temporary drawer opened by the app-bar menu button.
            The nav landmark lives on the SideNav list so it travels with the
            list into this drawer's portal (only one copy is ever in the a11y
            tree; the other is display:none). */}
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={() => setMobileOpen(false)}
          ModalProps={{ keepMounted: true }}
          sx={{
            display: { xs: 'block', lg: 'none' },
            '& .MuiDrawer-paper': { width: DRAWER_WIDTH, boxSizing: 'border-box' },
          }}
        >
          <Toolbar />
          {nav}
        </Drawer>
        {/* lg+: a permanent drawer, separated by a border (flat elevation, §6). */}
        <Drawer
          variant="permanent"
          open
          sx={{
            display: { xs: 'none', lg: 'block' },
            '& .MuiDrawer-paper': {
              width: DRAWER_WIDTH,
              boxSizing: 'border-box',
              borderInlineEnd: '1px solid',
              borderColor: 'divider',
            },
          }}
        >
          <Toolbar />
          {nav}
        </Drawer>
      </Box>

      <Box
        component="main"
        id="main-content"
        tabIndex={-1}
        sx={{ flexGrow: 1, width: { lg: `calc(100% - ${DRAWER_WIDTH}px)` }, outline: 'none' }}
      >
        <Toolbar />
        <Container maxWidth="lg" sx={{ py: 4 }}>
          <Outlet />
        </Container>
      </Box>
    </Box>
  )
}
