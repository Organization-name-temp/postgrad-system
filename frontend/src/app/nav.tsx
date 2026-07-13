import DashboardIcon from '@mui/icons-material/Dashboard'
import AccountTreeIcon from '@mui/icons-material/AccountTree'
import ApartmentIcon from '@mui/icons-material/Apartment'
import SchoolIcon from '@mui/icons-material/School'
import MenuBookIcon from '@mui/icons-material/MenuBook'
import type { NavItem } from './SideNav'
import { PERMISSION } from '../shared/access/permissions'

/**
 * The app's navigation destinations. Each later phase (and each catalog entity)
 * appends its own entries, with the permission that gates them. The dev-only
 * `/style` gallery link is added by the ticket that builds that route.
 */
export const navItems: NavItem[] = [
  { key: 'dashboard', to: '/', icon: <DashboardIcon /> },
  {
    key: 'faculties',
    to: '/catalog/faculties',
    icon: <AccountTreeIcon />,
    permission: PERMISSION.viewCatalog,
  },
  {
    key: 'departments',
    to: '/catalog/departments',
    icon: <ApartmentIcon />,
    permission: PERMISSION.viewCatalog,
  },
  {
    key: 'programs',
    to: '/catalog/programs',
    icon: <SchoolIcon />,
    permission: PERMISSION.viewCatalog,
  },
  {
    key: 'courses',
    to: '/catalog/courses',
    icon: <MenuBookIcon />,
    permission: PERMISSION.viewCatalog,
  },
]
