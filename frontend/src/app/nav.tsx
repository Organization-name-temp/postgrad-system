import DashboardIcon from '@mui/icons-material/Dashboard'
import AccountTreeIcon from '@mui/icons-material/AccountTree'
import type { NavItem } from './SideNav'
import { PERMISSION } from '../shared/access/permissions'

/**
 * The app's navigation destinations. Each later phase appends its own entries
 * (with the permission that gates them, if any). The dev-only `/style` gallery
 * link is added by the ticket that builds that route.
 */
export const navItems: NavItem[] = [
  { key: 'dashboard', to: '/', icon: <DashboardIcon /> },
  {
    key: 'catalog',
    to: '/catalog/faculties',
    icon: <AccountTreeIcon />,
    permission: PERMISSION.viewCatalog,
  },
]
