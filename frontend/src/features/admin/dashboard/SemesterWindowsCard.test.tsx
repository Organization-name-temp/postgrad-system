import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { SemesterWindowsCard } from './SemesterWindowsCard'
import { useSemesterAndOpenWindowsCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useSemesterAndOpenWindowsCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useSemesterAndOpenWindowsCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <SemesterWindowsCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('SemesterWindowsCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('names the live semester, every open window, and the next date due', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            semester: {
              id: 1,
              term: 'Fall',
              year: 2026,
              name_en: 'Fall 2026',
              name_ar: 'خريف 2026',
            },
            current_period: 'add_drop',
            // Plural on purpose: registration can still be open past the semester start.
            open_windows: ['registration', 'add_drop'],
            next_deadline: { window: 'add_drop', at: '2026-09-14T00:00:00+00:00' },
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('Fall 2026')).toBeInTheDocument()
    expect(screen.getByText('Registration open')).toBeInTheDocument()
    expect(screen.getByText('Add/drop open')).toBeInTheDocument()
    expect(screen.getByText(/Next: Add\/drop ends/)).toBeInTheDocument()
  })

  it('says so plainly when a live semester has no window open', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            semester: { id: 1, term: 'Fall', year: 2026, name_en: 'Fall 2026', name_ar: 'خريف' },
            current_period: 'closed',
            open_windows: [],
            next_deadline: { window: 'semester_end', at: '2026-12-20T00:00:00+00:00' },
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('No window is open')).toBeInTheDocument()
    expect(screen.getByText(/Next: Semester ends/)).toBeInTheDocument()
  })

  it('renders a meaningful empty state when no semester is live, not a blank', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: { semester: null, current_period: null, open_windows: [], next_deadline: null },
        },
      }),
    )
    renderCard()

    expect(
      screen.getByText('No semester is running, and none is open for registration.'),
    ).toBeInTheDocument()
  })

  it('links into semester management behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /Manage semesters/ })).toHaveAttribute(
      'href',
      '/admin/semesters',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the semester summary.')
  })
})
