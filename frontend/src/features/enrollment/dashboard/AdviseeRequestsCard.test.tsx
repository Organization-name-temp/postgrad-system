import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { AdviseeRequestsCard } from './AdviseeRequestsCard'
import { useAdviseeRequestsCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useAdviseeRequestsCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useAdviseeRequestsCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <AdviseeRequestsCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('AdviseeRequestsCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the pending count, the advisee count, and the oldest requests by student name', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 2,
            advisee_count: 7,
            requests: [
              {
                id: 11,
                student_id: 5,
                student_name: 'Sara Student',
                university_id: '2026-MSC-CE-0001',
                course_count: 2,
              },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('2 requests awaiting you')).toBeInTheDocument()
    expect(screen.getByText('across 7 advisees')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Sara Student' })).toHaveAttribute(
      'href',
      '/advising/11',
    )
    expect(screen.getByText('· 2 courses')).toBeInTheDocument()
  })

  it('omits the advisee row for an oversight reviewer, who holds none', () => {
    // Null, not zero: Student Affairs reviews unscoped, so an advisee total would be misleading.
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 3,
            advisee_count: null,
            requests: [],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('3 requests awaiting you')).toBeInTheDocument()
    expect(screen.queryByText(/advisee/)).not.toBeInTheDocument()
  })

  it('falls back to the University ID when a student’s name cannot be resolved', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 1,
            advisee_count: 1,
            requests: [
              { id: 11, student_id: 5, university_id: '2026-MSC-CE-0001', course_count: 1 },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByRole('link', { name: '2026-MSC-CE-0001' })).toBeInTheDocument()
  })

  it('renders the shared empty state for an empty queue, not a bare zero', () => {
    mockedQuery.mockReturnValue(
      queryResult({ data: { data: { pending_count: 0, advisee_count: 7, requests: [] } } }),
    )
    renderCard()

    expect(
      screen.getByText('No registration requests are waiting on you. You are up to date.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/0 requests/)).not.toBeInTheDocument()
  })

  it('links into the full queue behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Open the advisor queue/ })).toHaveAttribute(
      'href',
      '/advising',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the advisee requests.')
  })
})
