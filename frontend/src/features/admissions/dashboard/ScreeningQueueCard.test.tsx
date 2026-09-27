import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ScreeningQueueCard } from './ScreeningQueueCard'
import { useScreeningQueueCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useScreeningQueueCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useScreeningQueueCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <ScreeningQueueCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('ScreeningQueueCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the pending count, the overdue count, and the oldest applications by name and age', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 6,
            overdue_count: 2,
            sla_days: 5,
            applications: [
              {
                id: 1,
                status: 'under_sa_review',
                name_en: 'Sara Applicant',
                name_ar: 'سارة المتقدمة',
                age_days: 7,
                is_overdue: true,
              },
              {
                id: 2,
                status: 'under_sa_review',
                name_en: 'Omar Applicant',
                name_ar: 'عمر المتقدم',
                age_days: 2,
                is_overdue: false,
              },
            ],
          },
        },
      }),
    )
    renderCard()

    // FR-ADM-005.5: the count and the aging, on the Student Affairs dashboard.
    expect(screen.getByText('6 applications waiting')).toBeInTheDocument()
    // Overdue is distinguished from merely pending, and says what the target is.
    expect(screen.getByText('2 past the 5-day review target')).toBeInTheDocument()
    expect(screen.getByText('Sara Applicant')).toBeInTheDocument()
    const overdueText = screen.getByText('overdue — 7 business days')
    // Browser axe checks own real light/dark contrast; JSDOM cannot resolve CSS variables.
    expect(overdueText).toBeInTheDocument()
    expect(screen.getByText('waiting 2 business days')).toBeInTheDocument()
  })

  it('omits the overdue chip when nothing has passed the review target', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 1,
            overdue_count: 0,
            sla_days: 5,
            applications: [
              { id: 1, name_en: 'Sara Applicant', name_ar: 'س', age_days: 1, is_overdue: false },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.queryByText(/review target/)).not.toBeInTheDocument()
  })

  it('offers an overdue application the same link as any other — the flag blocks nothing', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 1,
            overdue_count: 1,
            sla_days: 5,
            applications: [
              { id: 42, name_en: 'Sara Applicant', name_ar: 'س', age_days: 9, is_overdue: true },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByRole('link', { name: 'Sara Applicant' })).toHaveAttribute(
      'href',
      '/screening/42',
    )
  })

  it('renders the shared empty state for an empty queue, not a bare zero', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: { data: { pending_count: 0, overdue_count: 0, sla_days: 5, applications: [] } },
      }),
    )
    renderCard()

    expect(
      screen.getByText('Nothing is waiting to be screened. You are up to date.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/0 applications/)).not.toBeInTheDocument()
  })

  it('links into the full queue behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Open the screening queue/ })).toHaveAttribute(
      'href',
      '/screening',
    )
  })

  it('renders its own error state and retries when the summary cannot be loaded', async () => {
    const refetch = vi.fn()
    mockedQuery.mockReturnValue(queryResult({ isError: true, refetch }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the screening queue.')
    await userEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalledOnce()
  })
})
