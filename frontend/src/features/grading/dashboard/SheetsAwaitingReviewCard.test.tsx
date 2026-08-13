import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { SheetsAwaitingReviewCard } from './SheetsAwaitingReviewCard'
import { useSheetsAwaitingReviewCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useSheetsAwaitingReviewCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useSheetsAwaitingReviewCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <SheetsAwaitingReviewCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('SheetsAwaitingReviewCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the waiting count and which offerings the sheets belong to', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            pending_count: 2,
            sheets: [
              {
                id: 4,
                course_offering_id: 3,
                kind: 'Initial',
                course: {
                  id: 1,
                  code: 'DEP501',
                  name_en: 'Advanced Algorithms',
                  name_ar: 'خوارزميات متقدمة',
                },
              },
            ],
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('2 sheets awaiting you')).toBeInTheDocument()
    // Each row links into that Sheet's own review screen, so the Card is a way in.
    expect(screen.getByRole('link', { name: 'DEP501 · Advanced Algorithms' })).toHaveAttribute(
      'href',
      '/grading/review/4',
    )
  })

  it('renders the shared empty state for an empty queue, not a bare zero', () => {
    // Also the Instructor case: they hold `grades.finalize` but head no Department, so the Card is
    // legitimately empty rather than broken.
    mockedQuery.mockReturnValue(queryResult({ data: { data: { pending_count: 0, sheets: [] } } }))
    renderCard()

    expect(
      screen.getByText('No grade sheets are waiting on your review. You are up to date.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/0 sheets/)).not.toBeInTheDocument()
  })

  it('links into the full review queue behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Open the review queue/ })).toHaveAttribute(
      'href',
      '/grading/review',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the review queue.')
  })
})
