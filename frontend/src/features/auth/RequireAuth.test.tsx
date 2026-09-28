import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '../../shared/i18n/config'
import { RequireAuth } from './RequireAuth'
import { useAuth } from './useAuth'
import type { UseAuth } from './useAuth'

vi.mock('./useAuth')

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

function renderGuard(permission?: string) {
  return render(
    <MemoryRouter initialEntries={['/private']}>
      <Routes>
        <Route path="/login" element={<div>Login screen</div>} />
        <Route path="/" element={<div>Dashboard home</div>} />
        <Route element={<RequireAuth permission={permission} />}>
          <Route path="/private" element={<div>Secret dashboard</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

function renderRoleGuard(roles: readonly string[]) {
  return render(
    <MemoryRouter initialEntries={['/private']}>
      <Routes>
        <Route path="/login" element={<div>Login screen</div>} />
        <Route path="/" element={<div>Dashboard home</div>} />
        <Route element={<RequireAuth roles={roles} />}>
          <Route path="/private" element={<div>Secret dashboard</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('RequireAuth', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en')
    vi.clearAllMocks()
  })

  it('renders the protected content when authenticated', () => {
    mockedUseAuth.mockReturnValue(authState({ isAuthenticated: true, user: { id: 1 } }))

    renderGuard()

    expect(screen.getByText('Secret dashboard')).toBeInTheDocument()
    expect(screen.queryByText('Login screen')).not.toBeInTheDocument()
  })

  it('redirects to the login page when unauthenticated', () => {
    mockedUseAuth.mockReturnValue(authState({ isAuthenticated: false }))

    renderGuard()

    expect(screen.getByText('Login screen')).toBeInTheDocument()
    expect(screen.queryByText('Secret dashboard')).not.toBeInTheDocument()
  })

  it('shows a spinner while the session is still resolving', () => {
    mockedUseAuth.mockReturnValue(authState({ isPending: true }))

    renderGuard()

    expect(screen.getByLabelText('Checking your session')).toBeInTheDocument()
    expect(screen.queryByText('Secret dashboard')).not.toBeInTheDocument()
    expect(screen.queryByText('Login screen')).not.toBeInTheDocument()
  })

  it('labels session loading in Arabic', async () => {
    await i18n.changeLanguage('ar')
    mockedUseAuth.mockReturnValue(authState({ isPending: true }))
    renderGuard()
    expect(screen.getByRole('progressbar', { name: 'جارٍ التحقق من جلستك' })).toBeInTheDocument()
  })

  it('renders the route when the required permission is held', () => {
    mockedUseAuth.mockReturnValue(
      authState({
        isAuthenticated: true,
        user: { id: 1 },
        permissions: ['catalog.manage'],
        hasPermission: (permission) => permission === 'catalog.manage',
      }),
    )

    renderGuard('catalog.manage')

    expect(screen.getByText('Secret dashboard')).toBeInTheDocument()
  })

  it('redirects an authenticated user who lacks the required permission', () => {
    mockedUseAuth.mockReturnValue(
      authState({
        isAuthenticated: true,
        user: { id: 1 },
        permissions: [],
        hasPermission: () => false,
      }),
    )

    renderGuard('catalog.manage')

    expect(screen.getByText('Dashboard home')).toBeInTheDocument()
    expect(screen.queryByText('Secret dashboard')).not.toBeInTheDocument()
  })

  it('renders the route when the user holds one of the required roles (persona guard)', () => {
    mockedUseAuth.mockReturnValue(
      authState({
        isAuthenticated: true,
        user: { id: 1 },
        roles: ['R01'],
        hasRole: (role) => role === 'R01',
      }),
    )

    renderRoleGuard(['R01'])

    expect(screen.getByText('Secret dashboard')).toBeInTheDocument()
  })

  it('redirects an authenticated user who lacks the required role', () => {
    // An admin (holds every permission) hitting a persona route they are not the Role for.
    mockedUseAuth.mockReturnValue(
      authState({
        isAuthenticated: true,
        user: { id: 1 },
        roles: ['R09'],
        hasRole: (role) => role === 'R09',
      }),
    )

    renderRoleGuard(['R01'])

    expect(screen.getByText('Dashboard home')).toBeInTheDocument()
    expect(screen.queryByText('Secret dashboard')).not.toBeInTheDocument()
  })
})
