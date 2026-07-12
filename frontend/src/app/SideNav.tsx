import { Box, List, ListItem, ListItemButton, ListItemIcon, ListItemText } from '@mui/material'
import type { ReactElement } from 'react'
import { Link as RouterLink, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../features/auth/useAuth'

export interface NavItem {
  /** Key under the `nav` group in the `common` namespace. */
  key: string
  to: string
  icon: ReactElement
  /** Effective permission required to see this item; absent = always visible. */
  permission?: string
}

/**
 * Role-aware navigation: renders only the items whose permission the signed-in
 * user holds — a display-only mirror of the API authority (ADR-0008). The active
 * item gets a leading brand-accent border (logical property, so it mirrors in
 * RTL). `onNavigate` lets the mobile drawer close on selection.
 */
export function SideNav({ items, onNavigate }: { items: NavItem[]; onNavigate?: () => void }) {
  const { t } = useTranslation('common')
  const { hasPermission } = useAuth()
  const { pathname } = useLocation()
  const visible = items.filter((item) => !item.permission || hasPermission(item.permission))

  // The `nav` landmark wraps the list (not the List itself — `List component="nav"`
  // renders <nav><li>, which is invalid). It travels with SideNav into the mobile
  // drawer's portal; the permanent + temporary copies are never both in the a11y
  // tree (the hidden drawer is display:none), so there's no duplicate landmark.
  return (
    <Box component="nav" aria-label={t('nav.label')}>
      <List>
        {visible.map((item) => {
          const selected = pathname === item.to
          return (
            <ListItem key={item.key} disablePadding>
              <ListItemButton
                component={RouterLink}
                to={item.to}
                selected={selected}
                aria-current={selected ? 'page' : undefined}
                onClick={onNavigate}
                sx={{
                  borderInlineStart: '3px solid transparent',
                  '&.Mui-selected': { borderInlineStartColor: 'primary.main' },
                }}
              >
                <ListItemIcon sx={{ minWidth: 40 }}>{item.icon}</ListItemIcon>
                <ListItemText primary={t(`nav.${item.key}`)} />
              </ListItemButton>
            </ListItem>
          )
        })}
      </List>
    </Box>
  )
}
