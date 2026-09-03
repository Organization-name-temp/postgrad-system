import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { CatalogSummaryCard } from './CatalogSummaryCard'
import { useCatalogSummaryCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useCatalogSummaryCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useCatalogSummaryCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <CatalogSummaryCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('CatalogSummaryCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the active program and course counts', () => {
    mockedQuery.mockReturnValue(
      queryResult({ data: { data: { active_program_count: 6, active_course_count: 42 } } }),
    )
    renderCard()

    expect(screen.getByText('Active programs')).toBeInTheDocument()
    expect(screen.getByText('6')).toBeInTheDocument()
    expect(screen.getByText('Active courses')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
  })

  it('says the catalog is unpopulated in words rather than showing a pair of zeros', () => {
    mockedQuery.mockReturnValue(
      queryResult({ data: { data: { active_program_count: 0, active_course_count: 0 } } }),
    )
    renderCard()

    expect(screen.getByText('Nothing is active in the catalog yet.')).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })

  it('still shows the counts when only one of the two is zero', () => {
    mockedQuery.mockReturnValue(
      queryResult({ data: { data: { active_program_count: 3, active_course_count: 0 } } }),
    )
    renderCard()

    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('0')).toBeInTheDocument()
  })

  it('links into the catalog behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Browse the catalog/ })).toHaveAttribute(
      'href',
      '/catalog',
    )
  })

  it('renders its own error state and retries when the summary cannot be loaded', async () => {
    const refetch = vi.fn()
    mockedQuery.mockReturnValue(queryResult({ isError: true, refetch }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the catalog summary.')
    await userEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalledOnce()
  })
})
