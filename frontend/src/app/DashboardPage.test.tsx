import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import '../shared/i18n/config' // initialise i18next so useTranslation works
import { DashboardPage } from './DashboardPage'
import type { DashboardCardEntry } from './dashboardCards'
import { ADMIN_ROLES, ROLE } from '../shared/access/roles'
import { useAuth } from '../features/auth/useAuth'
import type { UseAuth } from '../features/auth/useAuth'

vi.mock('../features/auth/useAuth')
const mockedUseAuth = vi.mocked(useAuth)

// The registry's real Card components each own a query hook, so the composition test mocks the
// registry itself: what is under test is *which* Cards the page selects for a given access set,
// not what any one Card renders (that is each Card's own test, plus DashboardCard's).
vi.mock('./dashboardCards', () => {
  const stub = (name: string) => () => <div>{name}</div>
  const cards: DashboardCardEntry[] = [
    { key: 'permissionCard', component: stub('permission card'), permission: 'application.screen' },
    // `ROLE.student` inlined: a vi.mock factory is hoisted above the imports, so it cannot
    // read a top-level binding.
    { key: 'personaCard', component: stub('persona card'), roles: ['R02'] },
    { key: 'adminCard', component: stub('admin card'), adminOnly: true },
  ]

  return { dashboardCards: cards }
})

function authState(overrides: Partial<UseAuth>): UseAuth {
  return {
    user: { name: 'Layla Samir', email: 'layla@ejust.test', roles: [] },
    isPending: false,
    isAuthenticated: true,
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

function renderDashboard() {
  return render(
    <MemoryRouter>
      <DashboardPage />
    </MemoryRouter>,
  )
}

describe('DashboardPage — the identity header', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the name, the roles held, and no wall of permission chips', () => {
    mockedUseAuth.mockReturnValue(
      authState({
        user: {
          name: 'Layla Samir',
          email: 'layla@ejust.test',
          roles: [{ code: 'R02', name: 'Student' }],
        },
        permissions: ['grades.view_own', 'courses.register_self'],
        hasPermission: () => true,
      }),
    )
    renderDashboard()

    expect(screen.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeInTheDocument()
    expect(screen.getByText('Layla Samir')).toBeInTheDocument()
    expect(screen.getByText('R02 · Student')).toBeInTheDocument()
    // The Phase-0 permission wall is gone — the page is a product, not a debugging tool.
    expect(screen.queryByText('grades.view_own')).not.toBeInTheDocument()
    expect(screen.queryByText('courses.register_self')).not.toBeInTheDocument()
  })

  it('keeps a Student’s University ID above the fold (ADR-0013)', () => {
    mockedUseAuth.mockReturnValue(
      authState({
        user: {
          name: 'Layla Samir',
          email: 'layla@ejust.test',
          roles: [{ code: 'R02', name: 'Student' }],
          student: { university_id: '2026CE0001' },
        },
      }),
    )
    renderDashboard()

    expect(screen.getByText('2026CE0001')).toBeInTheDocument()
  })

  it('omits the University ID block for a user with no Student anchor', () => {
    mockedUseAuth.mockReturnValue(authState({}))
    renderDashboard()

    expect(screen.queryByText('University ID')).not.toBeInTheDocument()
  })
})

describe('DashboardPage — card composition (ADR-0024)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows a permission-gated card to a user holding its permission, and no other', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: (p) => p === 'application.screen' }))
    renderDashboard()

    expect(screen.getByText('permission card')).toBeInTheDocument()
    expect(screen.queryByText('persona card')).not.toBeInTheDocument()
    expect(screen.queryByText('admin card')).not.toBeInTheDocument()
  })

  it('hides a permission-gated card from a user without the permission', () => {
    mockedUseAuth.mockReturnValue(authState({ hasPermission: () => false }))
    renderDashboard()

    expect(screen.queryByText('permission card')).not.toBeInTheDocument()
  })

  it('shows a persona card to the role that holds the record behind it', () => {
    mockedUseAuth.mockReturnValue(authState({ hasRole: (r) => r === ROLE.student }))
    renderDashboard()

    expect(screen.getByText('persona card')).toBeInTheDocument()
  })

  it('shows the union of every role’s cards to a multi-role user, on one page', () => {
    // The property the whole phase rests on: no role switcher, no hidden mode — one page.
    mockedUseAuth.mockReturnValue(
      authState({
        hasPermission: (p) => p === 'application.screen',
        hasRole: (r) => r === ROLE.student,
      }),
    )
    renderDashboard()

    expect(screen.getByText('permission card')).toBeInTheDocument()
    expect(screen.getByText('persona card')).toBeInTheDocument()
  })

  it('shows an administrator only the administration set, never the union they inherit', () => {
    // An admin holds every Permission (ADR-0008); that is an authorization fact, not a
    // description of their job.
    mockedUseAuth.mockReturnValue(
      authState({
        hasPermission: () => true,
        isAdmin: true,
        hasRole: (r) => ADMIN_ROLES.includes(r),
      }),
    )
    renderDashboard()

    expect(screen.getByText('admin card')).toBeInTheDocument()
    expect(screen.queryByText('permission card')).not.toBeInTheDocument()
  })

  it('withholds a persona card from an administrator who inherits its permission', () => {
    // The subtle one: an admin inherits `grades.view_own` but holds no Student anchor, so the
    // Card would render broken and the server would 403 its endpoint.
    mockedUseAuth.mockReturnValue(
      authState({
        hasPermission: () => true,
        isAdmin: true,
        hasRole: (r) => ADMIN_ROLES.includes(r),
      }),
    )
    renderDashboard()

    expect(screen.queryByText('persona card')).not.toBeInTheDocument()
  })

  it('tells a user with no cards that nothing is waiting, rather than rendering a blank page', () => {
    mockedUseAuth.mockReturnValue(authState({}))
    renderDashboard()

    expect(screen.getByText('There is nothing waiting for you here yet.')).toBeInTheDocument()
  })
})
