import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { MyGradeSheetsCard } from './MyGradeSheetsCard'
import { useMyGradeSheetsCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useMyGradeSheetsCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useMyGradeSheetsCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

const course = {
  id: 1,
  code: 'DEP501',
  name_en: 'Advanced Algorithms',
  name_ar: 'خوارزميات متقدمة',
}

/** A populated Card payload, overridable per case. */
function payload(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      data: {
        semester: { id: 2, term: 'Fall', year: 2026, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
        grade_submission_deadline_at: '2027-01-10T00:00:00+00:00',
        offering_count: 2,
        unfinalized_count: 1,
        overdue_count: 0,
        offerings: [{ offering_id: 3, sheet_status: 'Draft', is_overdue: false, course }],
        ...overrides,
      },
    },
  }
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <MyGradeSheetsCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('MyGradeSheetsCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the not-yet-finalized count and this term’s teaching load', () => {
    mockedQuery.mockReturnValue(queryResult(payload()))
    renderCard()

    expect(screen.getByText('1 sheet not yet finalized')).toBeInTheDocument()
    expect(screen.getByText('Fall 2026 · 2 offerings')).toBeInTheDocument()
  })

  it('links each row into that offering’s own sheet', () => {
    mockedQuery.mockReturnValue(queryResult(payload()))
    renderCard()

    expect(screen.getByRole('link', { name: 'DEP501 · Advanced Algorithms' })).toHaveAttribute(
      'href',
      '/grading/offerings/3',
    )
  })

  it('flags an offering past the submission deadline, and still links it — the flag is a nudge', () => {
    mockedQuery.mockReturnValue(
      queryResult(
        payload({
          overdue_count: 1,
          offerings: [{ offering_id: 3, sheet_status: 'Draft', is_overdue: true, course }],
        }),
      ),
    )
    renderCard()

    expect(screen.getByText('1 past the submission deadline')).toBeInTheDocument()
    expect(screen.getByText('Past deadline')).toBeInTheDocument()
    // Nothing is blocked by the flag: the Sheet behind it is as reachable as any other.
    expect(screen.getByRole('link', { name: 'DEP501 · Advanced Algorithms' })).toHaveAttribute(
      'href',
      '/grading/offerings/3',
    )
  })

  it('shows no deadline flag when nothing is overdue', () => {
    mockedQuery.mockReturnValue(queryResult(payload()))
    renderCard()

    expect(screen.queryByText('Past deadline')).not.toBeInTheDocument()
    expect(screen.queryByText(/past the submission deadline/)).not.toBeInTheDocument()
  })

  it('renders the empty state for a user with nothing to grade this term', () => {
    mockedQuery.mockReturnValue(
      queryResult(
        payload({
          semester: null,
          grade_submission_deadline_at: null,
          offering_count: 0,
          unfinalized_count: 0,
          offerings: [],
        }),
      ),
    )
    renderCard()

    expect(screen.getByText('You have nothing to grade this term.')).toBeInTheDocument()
    expect(screen.queryByText(/0 sheets/)).not.toBeInTheDocument()
  })

  it('renders the empty state when the academic term exists but has no scoped offerings', () => {
    mockedQuery.mockReturnValue(
      queryResult(payload({ offering_count: 0, unfinalized_count: 0, offerings: [] })),
    )
    renderCard()

    expect(screen.getByText('You have nothing to grade this term.')).toBeInTheDocument()
    expect(screen.queryByText(/0 sheets/)).not.toBeInTheDocument()
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load your grade sheets.')
  })
})
