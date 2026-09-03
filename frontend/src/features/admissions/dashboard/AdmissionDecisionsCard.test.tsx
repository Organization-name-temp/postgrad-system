import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { AdmissionDecisionsCard } from './AdmissionDecisionsCard'
import { useAdmissionDecisionsCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useAdmissionDecisionsCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useAdmissionDecisionsCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <AdmissionDecisionsCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('AdmissionDecisionsCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows how many applications are awaiting the Dean’s decision', () => {
    mockedQuery.mockReturnValue(queryResult({ data: { data: { pending_count: 3 } } }))
    renderCard()

    expect(screen.getByText('3 applications awaiting your decision')).toBeInTheDocument()
  })

  it('speaks of a single waiting application in the singular', () => {
    mockedQuery.mockReturnValue(queryResult({ data: { data: { pending_count: 1 } } }))
    renderCard()

    expect(screen.getByText('1 application awaiting your decision')).toBeInTheDocument()
  })

  it('reads an empty queue as good news rather than as a bare zero', () => {
    mockedQuery.mockReturnValue(queryResult({ data: { data: { pending_count: 0 } } }))
    renderCard()

    expect(
      screen.getByText('No applications are waiting on your decision. You are up to date.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/0 applications/)).not.toBeInTheDocument()
  })

  it('links into the decisions queue behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Open the decisions queue/ })).toHaveAttribute(
      'href',
      '/dean/decisions',
    )
  })

  it('renders its own error state when the count cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the decisions queue.')
  })
})
