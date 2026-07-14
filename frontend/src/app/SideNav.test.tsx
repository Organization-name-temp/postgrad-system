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
})
