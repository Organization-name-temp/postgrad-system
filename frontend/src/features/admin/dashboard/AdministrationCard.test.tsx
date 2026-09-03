import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { AdministrationCard } from './AdministrationCard'
import { useAdministrationCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useAdministrationCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useAdministrationCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <AdministrationCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('AdministrationCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the user and role-assignment counts as two separate figures', () => {
    // Deliberately two numbers: a user may hold several roles, which is the fact the whole
    // permission-composed dashboard rests on.
    mockedQuery.mockReturnValue(
      queryResult({
        data: { data: { user_count: 24, role_assignment_count: 27, recent_activity: [] } },
      }),
    )
    renderCard()

    expect(screen.getByText('Users')).toBeInTheDocument()
    expect(screen.getByText('24')).toBeInTheDocument()
    expect(screen.getByText('Role assignments')).toBeInTheDocument()
    expect(screen.getByText('27')).toBeInTheDocument()
  })

  it('labels each audit action from its key, never from server-supplied text', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            user_count: 24,
            role_assignment_count: 27,
            recent_activity: [
              {
                id: 91,
                action: 'student.admitted',
                actor_user_id: 7,
                actor_name: 'Dr. Dean',
                at: '2026-08-10T09:15:00+00:00',
              },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('Student admitted — Dr. Dean · Aug 10, 2026')).toBeInTheDocument()
  })

  it('names an entry with no actor rather than leaving the line half-written', () => {
    // A failed sign-in has no actor by construction, and is exactly the event this Card exists
    // to surface.
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            user_count: 24,
            role_assignment_count: 27,
            recent_activity: [{ id: 92, action: 'login_failed', at: '2026-08-10T07:00:00+00:00' }],
          },
        },
      }),
    )
    renderCard()

    expect(
      screen.getByText('Failed sign-in — No signed-in user · Aug 10, 2026'),
    ).toBeInTheDocument()
  })

  it('falls back to the raw action key for an action it has no label for', () => {
    // A later phase adding an audit action must be legible here before anyone translates it.
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            user_count: 1,
            role_assignment_count: 1,
            recent_activity: [
              {
                id: 93,
                action: 'standing.probation_declared',
                actor_name: 'Layla Samir',
                at: '2026-08-10T07:00:00+00:00',
              },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(
      screen.getByText('standing.probation_declared — Layla Samir · Aug 10, 2026'),
    ).toBeInTheDocument()
  })

  it('says an empty audit log is empty, and still shows the counts beside it', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: { data: { user_count: 3, role_assignment_count: 3, recent_activity: [] } },
      }),
    )
    renderCard()

    expect(screen.getByText('Nothing has been recorded in the audit log yet.')).toBeInTheDocument()
    // The counts are the Card's primary content and never disappear behind the log's empty state.
    expect(screen.getAllByText('3')).toHaveLength(2)
  })

  it('links into the administration surfaces behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Manage assignments/ })).toHaveAttribute(
      'href',
      '/admin/assignments',
    )
  })

  it('renders its own error state and retries when the summary cannot be loaded', async () => {
    const refetch = vi.fn()
    mockedQuery.mockReturnValue(queryResult({ isError: true, refetch }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Could not load the administration summary.',
    )
    await userEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalledOnce()
  })
})
