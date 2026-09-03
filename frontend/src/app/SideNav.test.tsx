import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import '../shared/i18n/config' // initialise i18next so useTranslation works
import { SideNav } from './SideNav'
import type { NavItem } from './SideNav'
import { navItems } from './nav'
import { ADMIN_ROLES, ROLE } from '../shared/access/roles'
import { useAuth } from '../features/auth/useAuth'
import type { UseAuth } from '../features/auth/useAuth'

vi.mock('../features/auth/useAuth')
const mockedUseAuth = vi.mocked(useAuth)

function authState(overrides: Partial<UseAuth>): UseAuth {
  return {
    user: undefined,
    isPending: false,
    isAuthenticated: false,
    permissions: [],
    hasPermission: () => false,
    roles: [],
    hasRole: () => false,
    isAdmin: false,
    isLoggingIn: false,
    loginError: undefined,
    login: vi.fn(),
    logout: vi.fn(),
    isRegistering: false,
    registerError: undefined,
    register: vi.fn(),
    persistLocale: vi.fn(),
    ...overrides,
  }
}

const items: NavItem[] = [
  { key: 'dashboard', to: '/', icon: <span /> },
  { key: 'catalog', to: '/catalog', icon: <span />, permission: 'catalog.manage' },
  {
    key: 'assignments',
    to: '/admin/assignments',
    icon: <span />,
    section: 'administration',
    adminOnly: true,
  },
  { key: 'myInterviews', to: '/interviews/mine', icon: <span />, roles: ['R04'] },
]

function renderNav(initial = '/') {
  return render(
    <MemoryRouter initialEntries={[initial]}>
      <SideNav items={items} />
    </MemoryRouter>,
  )
}

describe('SideNav', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows unrestricted items to everyone', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: () => false }))
    const { container } = renderNav()
    expect(container.querySelector('a[href="/"]')).toBeInTheDocument()
  })

  it('hides an item whose permission the user lacks', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: () => false }))
    const { container } = renderNav()
    expect(container.querySelector('a[href="/catalog"]')).not.toBeInTheDocument()
  })

  it('shows a permission-gated item to a user who holds the permission', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: (p) => p === 'catalog.manage' }))
    const { container } = renderNav()
    expect(container.querySelector('a[href="/catalog"]')).toBeInTheDocument()
  })

  it('marks the active route as aria-current', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: () => true }))
    const { container } = renderNav('/')
    expect(container.querySelector('a[href="/"]')).toHaveAttribute('aria-current', 'page')
    expect(container.querySelector('a[href="/catalog"]')).not.toHaveAttribute('aria-current')
  })

  it('hides the admin-only Administration section from a non-admin', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: () => true, isAdmin: false }))
    const { container, queryByText } = renderNav()
    expect(container.querySelector('a[href="/admin/assignments"]')).not.toBeInTheDocument()
    expect(queryByText('Administration')).not.toBeInTheDocument()
  })

  it('shows the Administration section to an administrator', () => {
    mockedUseAuth.mockReturnValue(authState({ isAdmin: true }))
    const { container, getByText } = renderNav()
    expect(container.querySelector('a[href="/admin/assignments"]')).toBeInTheDocument()
    expect(getByText('Administration')).toBeInTheDocument()
  })

  it('hides a role-gated item from a user without the role', () => {
    mockedUseAuth.mockReturnValue(authState({ hasRole: () => false }))
    const { container } = renderNav()
    expect(container.querySelector('a[href="/interviews/mine"]')).not.toBeInTheDocument()
  })

  it('shows a role-gated item to a user who holds the role', () => {
    mockedUseAuth.mockReturnValue(authState({ hasRole: (r) => r === 'R04' }))
    const { container } = renderNav()
    expect(container.querySelector('a[href="/interviews/mine"]')).toBeInTheDocument()
  })
})

// Ticket 11 — persona gating and area grouping against the real navigation config.
function renderRealNav() {
  return render(
    <MemoryRouter>
      <SideNav items={navItems} />
    </MemoryRouter>,
  )
}

describe('SideNav — persona gating (ticket 11)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('hides "My application" from an admin who merely inherits the permission', () => {
    // An admin holds every permission (ADR-0008) but is not an Applicant (R01).
    mockedUseAuth.mockReturnValue(
      authState({
        hasPermission: () => true,
        isAdmin: true,
        hasRole: (r) => ADMIN_ROLES.some((adminRole) => adminRole === r),
      }),
    )
    const { container } = renderRealNav()
    expect(container.querySelector('a[href="/apply"]')).not.toBeInTheDocument()
  })

  it('shows "My application" to an actual Applicant (R01)', () => {
    mockedUseAuth.mockReturnValue(authState({ hasRole: (r) => r === ROLE.applicant }))
    const { container } = renderRealNav()
    expect(container.querySelector('a[href="/apply"]')).toBeInTheDocument()
  })

  it('shows reviewer capability items to a permission holder', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: (p) => p === 'application.screen' }))
    const { container } = renderRealNav()
    expect(container.querySelector('a[href="/screening"]')).toBeInTheDocument()
    // …but not the applicant persona item.
    expect(container.querySelector('a[href="/apply"]')).not.toBeInTheDocument()
  })
})

describe('SideNav — area grouping (ticket 11)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders an area subheader for a visible section and omits empty ones', () => {
    // A catalog viewer sees only Catalog items — no Admissions/Calendar/Administration.
    mockedUseAuth.mockReturnValue(authState({ hasPermission: (p) => p === 'catalog.view' }))
    renderRealNav()

    expect(screen.getByText('Catalog')).toBeInTheDocument()
    expect(screen.queryByText('Admissions')).not.toBeInTheDocument()
    expect(screen.queryByText('Calendar')).not.toBeInTheDocument()
    expect(screen.queryByText('Administration')).not.toBeInTheDocument()
  })

  it('renders multiple area subheaders when the user spans areas', () => {
    // Student Affairs holds application.screen + interview.schedule + calendar.manage.
    mockedUseAuth.mockReturnValue(
      authState({
        hasPermission: (p) =>
          ['application.screen', 'interview.schedule', 'calendar.manage'].includes(p),
      }),
    )
    renderRealNav()

    expect(screen.getByText('Admissions')).toBeInTheDocument()
    expect(screen.getByText('Calendar')).toBeInTheDocument()
    expect(screen.queryByText('Administration')).not.toBeInTheDocument()
  })
})
