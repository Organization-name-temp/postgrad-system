import DashboardIcon from '@mui/icons-material/Dashboard'
import AssignmentIcon from '@mui/icons-material/Assignment'
import TravelExploreIcon from '@mui/icons-material/TravelExplore'
import AccountTreeIcon from '@mui/icons-material/AccountTree'
import ApartmentIcon from '@mui/icons-material/Apartment'
import SchoolIcon from '@mui/icons-material/School'
import MenuBookIcon from '@mui/icons-material/MenuBook'
import EventNoteIcon from '@mui/icons-material/EventNote'
import FactCheckIcon from '@mui/icons-material/FactCheck'
import GavelIcon from '@mui/icons-material/Gavel'
import SupervisorAccountIcon from '@mui/icons-material/SupervisorAccount'
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
    key: 'apply',
    to: '/apply',
    icon: <AssignmentIcon />,
    permission: PERMISSION.submitApplication,
  },
  {
    key: 'screening',
    to: '/screening',
    icon: <FactCheckIcon />,
    permission: PERMISSION.screenApplications,
  },
  {
    key: 'deanReview',
    to: '/dean',
    icon: <GavelIcon />,
    permission: PERMISSION.screenApplications,
  },
  {
    key: 'browse',
    to: '/catalog',
    icon: <TravelExploreIcon />,
    permission: PERMISSION.viewCatalog,
  },
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
  {
    key: 'cycles',
    to: '/admin/cycles',
    icon: <EventNoteIcon />,
    permission: PERMISSION.manageCalendar,
  },
  // Administration (ticket 08): role-gated to System/Super Admins (R09/R10), grouped
  // under its own subheader — the first role-gated (not permission-gated) nav section.
  {
    key: 'assignments',
    to: '/admin/assignments',
    icon: <SupervisorAccountIcon />,
    section: 'administration',
    adminOnly: true,
  },
]
