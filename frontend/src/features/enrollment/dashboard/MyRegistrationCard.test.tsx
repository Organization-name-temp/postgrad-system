import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { MyRegistrationCard } from './MyRegistrationCard'
import { useMyRegistrationCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useMyRegistrationCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useMyRegistrationCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

/** A populated Card payload, overridable per case. */
function payload(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      data: {
        semester: { id: 1, term: 'Fall', year: 2026, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
        current_period: 'add_drop',
        period_ends_at: '2026-09-14T00:00:00+00:00',
        credit_hours: 9,
        min_credit_hours: 6,
        max_credit_hours: 12,
        enrollment_count: 3,
        ...overrides,
      },
    },
  }
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <MyRegistrationCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('MyRegistrationCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the credit-hour load with the 6–12 bounds beside it, so the number is anchored', () => {
    mockedQuery.mockReturnValue(queryResult(payload()))
    renderCard()

    expect(screen.getByText('9 credit hours')).toBeInTheDocument()
    expect(screen.getByText('of the 6–12 allowed')).toBeInTheDocument()
    expect(screen.getByText('Fall 2026')).toBeInTheDocument()
  })

  it('names the open period and when it closes', () => {
    mockedQuery.mockReturnValue(queryResult(payload()))
    renderCard()

    expect(screen.getByText('Add/drop open')).toBeInTheDocument()
    expect(screen.getByText(/closes/)).toBeInTheDocument()
  })

  it('links to the registration cart while registration is open', () => {
    // The ticket's point: the Card takes the Student to the action that is actually available.
    mockedQuery.mockReturnValue(
      queryResult(payload({ current_period: 'registration', period_ends_at: null })),
    )
    renderCard()

    expect(screen.getByRole('link', { name: /Go to registration/ })).toHaveAttribute(
      'href',
      '/registration',
    )
  })

  it('links to my schedule once registration is not the open period', () => {
    mockedQuery.mockReturnValue(queryResult(payload({ current_period: 'withdrawal' })))
    renderCard()

    expect(screen.getByRole('link', { name: /View my schedule/ })).toHaveAttribute(
      'href',
      '/schedule',
    )
  })

  it('links to registration during late-add, which is composed as a request', () => {
    mockedQuery.mockReturnValue(queryResult(payload({ current_period: 'late_add' })))
    renderCard()

    expect(screen.getByRole('link', { name: /Go to registration/ })).toBeInTheDocument()
  })

  it('says nothing is registered in words, never as "0 credit hours"', () => {
    // Ticket 04: a Student with no Enrollments in the current Semester gets a meaningful state
    // rather than a zero — while the open period and its deadline stay visible, because those are
    // what they need in order to act.
    mockedQuery.mockReturnValue(
      queryResult(
        payload({ current_period: 'registration', credit_hours: 0, enrollment_count: 0 }),
      ),
    )
    renderCard()

    expect(
      screen.getByText('You have not registered for any courses this semester.'),
    ).toBeInTheDocument()
    expect(screen.queryByText('0 credit hours')).not.toBeInTheDocument()
    expect(screen.queryByText(/of the 6–12 allowed/)).not.toBeInTheDocument()
    // Still actionable: the period is named and the link points at the cart.
    expect(screen.getByText('Registration open')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Go to registration/ })).toBeInTheDocument()
  })

  it('renders a meaningful empty state when no semester is open, not a zero', () => {
    mockedQuery.mockReturnValue(
      queryResult(
        payload({ semester: null, current_period: null, period_ends_at: null, credit_hours: 0 }),
      ),
    )
    renderCard()

    expect(screen.getByText('No semester is open for registration yet.')).toBeInTheDocument()
    expect(screen.queryByText(/0 credit hours/)).not.toBeInTheDocument()
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load your registration.')
  })
})

it.each([1, 2, 3, 11, 100])(
  'shows the actual Arabic credit-hour count %i rather than a singular fallback',
  async (count) => {
    const { default: i18n } = await import('../../../shared/i18n/config')
    await i18n.changeLanguage('ar')
    try {
      mockedQuery.mockReturnValue(queryResult(payload({ credit_hours: count })))
      renderCard()
      expect(screen.getByText(`الساعات المعتمدة: ${count}`)).toBeInTheDocument()
    } finally {
      await act(() => i18n.changeLanguage('en'))
    }
  },
)
