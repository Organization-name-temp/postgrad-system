import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../shared/i18n/config' // initialise i18next so useTranslation works
import { DashboardPage } from './DashboardPage'
import { ADMIN_ROLES } from '../shared/access/roles'
import { useAuth } from '../features/auth/useAuth'
import type { UseAuth } from '../features/auth/useAuth'

/*
| The administrator's Card set (phase 4.5, ticket 07) against the **real registry**.
|
| DashboardPage.test.tsx mocks the registry, because what it tests is the selection rule over a
| fixed set of stub Cards. This file tests the other half — that the registry the application
| actually ships composes to exactly the Administration Card plus the two configuration Cards for a
| System/Super Administrator, and to nothing else.
|
| Absence is asserted as firmly as presence, and that is the point. R09/R10 hold **every** Permission
| in the §3.2 matrix (ADR-0008), so a Card that forgot its Role gate, or an admin branch that fell
| back to the union, would silently stack ten other people's work queues onto an administrator's
| Dashboard. That is a regression a test must fail on, not one a reviewer must notice.
|
| Every Card owns a generated query hook, so the whole generated module is stubbed: each hook
| answers "loaded, empty" and each real Card renders its own real title. The stubs are derived from
| the module's own exports rather than listed by hand, so a Card added by Phase 5 or 6 joins this
| assertion instead of crashing it.
*/

vi.mock('../features/auth/useAuth')
const mockedUseAuth = vi.mocked(useAuth)

vi.mock('../shared/api/generated/endpoints', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>()
  const stub = () => ({ data: undefined, isPending: false, isError: false })

  // Every generated query hook answers "loaded, empty"; everything else passes through. Derived
  // from the module's own exports rather than listed by hand, so a Card added later needs no edit
  // here.
  return Object.fromEntries(
    Object.keys(actual).map((name) => [name, name.startsWith('use') ? stub : actual[name]]),
  )
})

// A real theme, unlike the sibling registry test: that one renders stub Cards, while this one
// renders the shipped ones, and they reach for theme tokens and `theme.direction`.
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** Every Card in the shipped registry, by the heading it renders. */
const ADMINISTRATION_SET = ['Administration', 'Semester and open windows', 'Catalog']
const EVERYTHING_ELSE = [
  'My application',
  'Screening queue',
  'Admission decisions',
  'My registration',
  'Advisee requests',
  'My grade sheets',
  'Sheets awaiting review',
  'Outstanding Incompletes',
  'My academic record',
]

const ROLE_CARD_CASES = [
  ['R01', ['My application'], []],
  ['R02', ['My registration', 'My academic record'], []],
  ['R03', ['Advisee requests'], ['registration.approve']],
  ['R04', ['My grade sheets', 'Outstanding Incompletes'], ['grades.enter', 'grades.finalize']],
  [
    'R05',
    ['Screening queue', 'Advisee requests', 'My grade sheets', 'Semester and open windows'],
    ['application.screen', 'registration.approve', 'grades.enter', 'calendar.manage'],
  ],
  ['R06', ['Sheets awaiting review', 'Catalog'], ['grades.finalize', 'catalog.manage']],
  [
    'R07',
    ['Screening queue', 'Admission decisions', 'Semester and open windows', 'Catalog'],
    ['application.screen', 'applicant.decide', 'calendar.manage', 'catalog.manage'],
  ],
  ['R08', [], []],
] as const

function authState(overrides: Partial<UseAuth>): UseAuth {
  return {
    user: {
      name: 'Root Admin',
      email: 'r09@ejust.test',
      roles: [{ code: 'R09', name: 'System Administrator' }],
    },
    isPending: false,
    isAuthenticated: true,
    permissions: [],
    // An administrator inherits every Permission and holds the admin Roles — the exact state that
    // would produce a ten-Card page under a naive union.
    hasPermission: () => true,
    roles: ['R09'],
    hasRole: (role: string) => ADMIN_ROLES.some((adminRole) => adminRole === role),
    isAdmin: true,
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
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <DashboardPage />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('DashboardPage — the administrator’s Card set, against the real registry', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders the Administration Card and the two configuration Cards', () => {
    mockedUseAuth.mockReturnValue(authState({}))
    renderDashboard()

    for (const title of ADMINISTRATION_SET) {
      expect(screen.getByRole('heading', { level: 2, name: title })).toBeInTheDocument()
    }
  })

  it('renders no other Card — not a queue, not a persona Card', () => {
    mockedUseAuth.mockReturnValue(authState({}))
    renderDashboard()

    for (const title of EVERYTHING_ELSE) {
      expect(screen.queryByRole('heading', { level: 2, name: title })).not.toBeInTheDocument()
    }
  })

  it('renders exactly three Cards, so a new one cannot join the set unnoticed', () => {
    mockedUseAuth.mockReturnValue(authState({}))
    renderDashboard()

    expect(screen.getAllByRole('heading', { level: 2 })).toHaveLength(ADMINISTRATION_SET.length)
  })

  it.each(['R09', 'R10'] as const)('renders the exact administration set for %s', (role) => {
    mockedUseAuth.mockReturnValue(
      authState({
        user: {
          name: role,
          email: `${role.toLowerCase()}@ejust.test`,
          roles: [{ code: role, name: role }],
        },
        roles: [role],
        hasRole: (candidate: string) => candidate === role,
      }),
    )
    renderDashboard()

    const titles = screen
      .queryAllByRole('heading', { level: 2 })
      .map((heading) => heading.textContent)
    expect(titles.sort()).toEqual([...ADMINISTRATION_SET].sort())
  })

  it('withholds the Administration Card from a non-administrator holding every permission a role can', () => {
    // Nothing in the §3.2 matrix opens this Card: it gates on the Role, and no Permission
    // substitutes for it — which is what the server's `admin` middleware enforces too.
    mockedUseAuth.mockReturnValue(
      authState({
        user: {
          name: 'Dr. Dean',
          email: 'r07@ejust.test',
          roles: [{ code: 'R07', name: 'Dean of Faculty' }],
        },
        roles: ['R07'],
        hasRole: (role: string) => role === 'R07',
        isAdmin: false,
      }),
    )
    renderDashboard()

    expect(
      screen.queryByRole('heading', { level: 2, name: 'Administration' }),
    ).not.toBeInTheDocument()
  })

  it('still shows the configuration Cards to the §3.2 permission holders who are not administrators', () => {
    // Membership of the admin set adds a Card to an administrator's Dashboard; it never removes
    // one from anyone else's.
    mockedUseAuth.mockReturnValue(
      authState({
        user: {
          name: 'Dr. Dean',
          email: 'r07@ejust.test',
          roles: [{ code: 'R07', name: 'Dean of Faculty' }],
        },
        roles: ['R07'],
        hasPermission: (permission: string) =>
          permission === 'calendar.manage' || permission === 'catalog.manage',
        hasRole: (role: string) => role === 'R07',
        isAdmin: false,
      }),
    )
    renderDashboard()

    expect(
      screen.getByRole('heading', { level: 2, name: 'Semester and open windows' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 2, name: 'Catalog' })).toBeInTheDocument()
  })

  it.each(ROLE_CARD_CASES)(
    'renders the exact Dashboard Card set for %s',
    (role, expected, permissions) => {
      mockedUseAuth.mockReturnValue(
        authState({
          user: {
            name: role,
            email: `${role.toLowerCase()}@ejust.test`,
            roles: [{ code: role, name: role }],
          },
          roles: [role],
          hasRole: (candidate: string) => candidate === role,
          hasPermission: (permission: string) =>
            permissions.some((candidate) => candidate === permission),
          isAdmin: false,
        }),
      )
      renderDashboard()

      const titles = screen
        .queryAllByRole('heading', { level: 2 })
        .map((heading) => heading.textContent)
      expect(titles).toEqual(expected)
    },
  )
})
