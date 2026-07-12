import DashboardIcon from '@mui/icons-material/Dashboard'
import type { NavItem } from './SideNav'

/**
 * The app's navigation destinations. Each later phase appends its own entries
 * (with the permission that gates them, if any); Phase 0.5 ships only the
 * dashboard. The dev-only `/style` gallery link is added by the ticket that
 * builds that route.
 */
export const navItems: NavItem[] = [{ key: 'dashboard', to: '/', icon: <DashboardIcon /> }]
