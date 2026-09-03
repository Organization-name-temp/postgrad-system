import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { MyApplicationCard } from './MyApplicationCard'
import { useMyApplicationCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useMyApplicationCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useMyApplicationCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <MyApplicationCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('MyApplicationCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the current status as a chip and what happens next', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            id: 1,
            status: 'under_sa_review',
            status_entered_at: '2026-07-10T10:00:00+00:00',
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('Under review')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Student Affairs is reviewing your application. Nothing is needed from you.',
      ),
    ).toBeInTheDocument()
  })

  it('tells an applicant whose application came back that the next move is theirs', () => {
    // The distinction the Card exists to draw: whether the university or the applicant is holding
    // things up.
    mockedQuery.mockReturnValue(
      queryResult({ data: { data: { id: 1, status: 'returned_for_revision' } } }),
    )
    renderCard()

    expect(
      screen.getByText('Your reviewer has asked for changes — address them and resubmit.'),
    ).toBeInTheDocument()
  })

  it('renders the empty state for an applicant who has not started, not an error', () => {
    mockedQuery.mockReturnValue(queryResult({ data: { data: null } }))
    renderCard()

    expect(screen.getByText('You have not started an application yet.')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('links into the application behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /View my application/ })).toHaveAttribute(
      'href',
      '/apply',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load your application.')
  })
})
