import {
  Box,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  ListSubheader,
} from '@mui/material'
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
  /**
   * Section key (under `nav.sections`) this item is grouped beneath. Absent = the
   * default, header-less top group. Sections render in first-seen order.
   */
  section?: string
  /**
   * When true, only a System/Super Administrator (R09/R10) sees the item — the
   * role gate behind the Administration section (ticket 08), distinct from the
   * permission gate (admins hold every permission, ADR-0008).
   */
  adminOnly?: boolean
  /**
   * Role codes any of which reveals the item — for a surface reached by role rather
   * than permission (e.g. the interviewer's queue, ticket 09: interviewers hold no
   * reviewer permission, so their nav entry is gated on the Instructor role). Absent =
   * no role gate.
   */
  roles?: readonly string[]
}

/**
 * Role-aware navigation: renders only the items the signed-in user may see — a
 * display-only mirror of the API authority (ADR-0008). An item is hidden unless the
 * user holds its `permission` (if any) and, for an `adminOnly` item, is an
 * administrator. Items carrying a `section` are grouped under a labelled subheader
 * (e.g. Administration), rendered only when at least one of its items is visible.
 * The active item gets a leading brand-accent border (logical property, so it
 * mirrors in RTL). `onNavigate` lets the mobile drawer close on selection.
 */
export function SideNav({ items, onNavigate }: { items: NavItem[]; onNavigate?: () => void }) {
  const { t } = useTranslation('common')
  const { hasPermission, hasRole, isAdmin } = useAuth()
  const { pathname } = useLocation()

  const visible = items.filter(
    (item) =>
      (!item.permission || hasPermission(item.permission)) &&
      (!item.adminOnly || isAdmin) &&
      (!item.roles || item.roles.some((role) => hasRole(role))),
  )
  const general = visible.filter((item) => !item.section)
  const sections = [...new Set(visible.map((item) => item.section).filter(Boolean))] as string[]

  const renderItem = (item: NavItem) => {
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
  }

  // The `nav` landmark wraps the lists (not a List itself — `List component="nav"`
  // renders <nav><li>, which is invalid). It travels with SideNav into the mobile
  // drawer's portal; the permanent + temporary copies are never both in the a11y
  // tree (the hidden drawer is display:none), so there's no duplicate landmark.
  return (
    <Box component="nav" aria-label={t('nav.label')}>
      <List>{general.map(renderItem)}</List>
      {sections.map((section) => (
        <List
          key={section}
          subheader={<ListSubheader disableSticky>{t(`nav.sections.${section}`)}</ListSubheader>}
        >
          {visible.filter((item) => item.section === section).map(renderItem)}
        </List>
      ))}
    </Box>
  )
}
