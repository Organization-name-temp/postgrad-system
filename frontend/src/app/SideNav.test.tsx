import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import '../shared/i18n/config' // initialise i18next so useTranslation works
import { SideNav } from './SideNav'
import type { NavItem } from './SideNav'
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
})
