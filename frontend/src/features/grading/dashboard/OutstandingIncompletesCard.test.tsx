import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { OutstandingIncompletesCard } from './OutstandingIncompletesCard'
import { useOutstandingIncompletesCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useOutstandingIncompletesCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useOutstandingIncompletesCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <OutstandingIncompletesCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('OutstandingIncompletesCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the count and the nearest resolution deadline', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            outstanding_count: 2,
            nearest_deadline: '2026-11-03T00:00:00+00:00',
            without_deadline_count: 0,
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('2 Incompletes still outstanding')).toBeInTheDocument()
    expect(screen.getByText('Nearest resolution deadline: Nov 3, 2026')).toBeInTheDocument()
    expect(screen.queryByText(/no deadline yet/)).not.toBeInTheDocument()
  })

  it('says the deadline cannot be worked out yet rather than inventing one', () => {
    // The whole set has no computable deadline: the following semester is not on the calendar.
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            outstanding_count: 1,
            nearest_deadline: undefined,
            without_deadline_count: 1,
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('1 Incomplete still outstanding')).toBeInTheDocument()
    expect(
      screen.getByText(
        'No resolution deadline yet — the following semester is not on the calendar.',
      ),
    ).toBeInTheDocument()
  })

  it('flags the undated ones beside a nearest deadline that other Incompletes do have', () => {
    // The mixed case: one Incomplete is dated and one is not, and the undated one must not
    // disappear behind the nearest deadline.
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            outstanding_count: 2,
            nearest_deadline: '2026-11-03T00:00:00+00:00',
            without_deadline_count: 1,
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('Nearest resolution deadline: Nov 3, 2026')).toBeInTheDocument()
    expect(screen.getByText('1 with no deadline yet')).toBeInTheDocument()
  })

  it('reads nothing outstanding as good news rather than as a bare zero', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: { data: { outstanding_count: 0, without_deadline_count: 0 } },
      }),
    )
    renderCard()

    expect(
      screen.getByText('You have no outstanding Incompletes. You are up to date.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/0 Incompletes/)).not.toBeInTheDocument()
  })

  it('links into the resolution screen behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Resolve Incompletes/ })).toHaveAttribute(
      'href',
      '/grading/incompletes',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Could not load your outstanding Incompletes.',
    )
  })
})
