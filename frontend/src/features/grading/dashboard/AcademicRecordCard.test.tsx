import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { AcademicRecordCard } from './AcademicRecordCard'
import { useMyAcademicRecordCard } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useMyAcademicRecordCard)

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

/** The slice of the query result this Card reads. */
type CardQuery = ReturnType<typeof useMyAcademicRecordCard>
function queryResult(overrides: Partial<CardQuery>): CardQuery {
  return { data: undefined, isPending: false, isError: false, ...overrides } as CardQuery
}

function renderCard() {
  return render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>
        <AcademicRecordCard />
      </MemoryRouter>
    </ThemeProvider>,
  )
}

describe('AcademicRecordCard', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the CGPA and the latest semester GPA to two decimals, with the earned hours', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            cgpa: 3.5,
            latest_semester: { id: 3, term: 'Fall', year: 2025, name_en: 'Fall 2025' },
            latest_semester_gpa: 4,
            earned_credit_hours: 6,
          },
        },
      }),
    )
    renderCard()

    expect(screen.getByText('3.50')).toBeInTheDocument()
    expect(screen.getByText('4.00')).toBeInTheDocument()
    expect(screen.getByText('Latest semester: Fall 2025')).toBeInTheDocument()
    expect(screen.getByText('6 credit hours earned so far')).toBeInTheDocument()
  })

  it('renders a dash for a record with no finalized grade yet — never 0.00 and never a bare 0', () => {
    mockedQuery.mockReturnValue(
      queryResult({
        data: {
          data: {
            cgpa: null,
            latest_semester: null,
            latest_semester_gpa: null,
            earned_credit_hours: 0,
          },
        },
      }),
    )
    renderCard()

    expect(screen.getAllByText('—')).toHaveLength(2)
    expect(screen.queryByText('0.00')).not.toBeInTheDocument()
    // "0 credit hours earned" would be exactly the bare zero the empty-state rule forbids.
    expect(screen.queryByText(/0 credit hours/)).not.toBeInTheDocument()
    expect(screen.getByText('No grades have been finalized yet.')).toBeInTheDocument()
  })

  it('links into the full record behind it', () => {
    mockedQuery.mockReturnValue(queryResult({}))
    renderCard()

    expect(screen.getByRole('link', { name: /View my grades/ })).toHaveAttribute(
      'href',
      '/grading/my-grades',
    )
  })

  it('renders its own error state when the summary cannot be loaded', () => {
    mockedQuery.mockReturnValue(queryResult({ isError: true }))
    renderCard()

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load your academic record.')
  })
})
