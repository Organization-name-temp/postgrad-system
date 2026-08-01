import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { MyGradesPage } from './MyGradesPage'

// StatusChip's soft/outlined variants read CSS-variable palette entries, so render under a
// cssVariables theme (the GradeSheetPage test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

// The My Grades read behind the shared Axios mutator, so the page drives the real generated
// query hook (fetch → group by semester → render CGPA/semester GPA/course rows).
let payload: unknown

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/grading/my-grades')) {
      return Promise.resolve(payload)
    }
    return Promise.resolve({ data: {} })
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MyGradesPage />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

const course = {
  id: 1,
  code: 'DEP501',
  name_en: 'Advanced Algorithms',
  name_ar: 'خوارزميات متقدمة',
  level: 500,
}

function semester(overrides: Record<string, unknown> = {}) {
  return {
    semester: { id: 2, term: 'Fall', year: 2026, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
    gpa: 3.0,
    courses: [
      {
        enrollment_id: 5,
        course,
        credit_hours: 3,
        grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3.0 },
        earned: true,
        superseded: false,
      },
    ],
    ...overrides,
  }
}

beforeEach(() => {
  payload = { data: { cgpa: null, semesters: [] } }
})

describe('MyGradesPage (ticket 04)', () => {
  it('renders the CGPA as a dash, and an empty message, before any graded course exists', async () => {
    renderPage()

    expect(await screen.findByText('—')).toBeInTheDocument()
    expect(screen.getByText(/no grades have been finalized yet/i)).toBeInTheDocument()
  })

  it('shows the CGPA, one Semester section with its own GPA, and each course’s facts', async () => {
    payload = { data: { cgpa: 3.0, semesters: [semester()] } }
    renderPage()

    // The CGPA header — formatted to two decimals, never a bare "3".
    expect(await screen.findByText('3.00')).toBeInTheDocument()
    // The Semester heading and its own GPA.
    expect(screen.getByRole('heading', { name: /fall 2026/i })).toBeInTheDocument()
    expect(screen.getByText(/semester gpa: 3\.00/i)).toBeInTheDocument()
    // The course row: code, letter, credit hours, and whether it earned its credit.
    expect(screen.getByText('DEP501')).toBeInTheDocument()
    expect(screen.getByText('B')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: /^earned credit$/i })).toBeInTheDocument()
  })

  it('shows a superseded attempt muted, with an explicit label, excluded from nothing visually hidden', async () => {
    payload = {
      data: {
        cgpa: 1.0,
        semesters: [
          semester({
            gpa: null,
            courses: [
              {
                enrollment_id: 5,
                course,
                credit_hours: 3,
                grade: { mark: 95, letter_grade: 'A+', gpa_points: 4.0 },
                earned: true,
                superseded: true,
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    // Still visible — the letter and course code are not hidden.
    expect(await screen.findByText('A+')).toBeInTheDocument()
    expect(screen.getByText('DEP501')).toBeInTheDocument()
    // Explicitly labelled, not just colour (WCAG 1.4.1).
    expect(screen.getByText(/superseded/i)).toBeInTheDocument()
    // A superseded semester with no other counted course shows the dash, not 0.00.
    expect(screen.getByText(/semester gpa: —/i)).toBeInTheDocument()
  })

  it('shows an Incomplete with its derived deadline, never a letter or an earned/not-earned label', async () => {
    payload = {
      data: {
        cgpa: null,
        semesters: [
          semester({
            gpa: null,
            courses: [
              {
                enrollment_id: 9,
                course,
                credit_hours: 3,
                course_status: 'Incomplete',
                grade: null,
                earned: null,
                superseded: false,
                incomplete_deadline: '2026-11-03T00:00:00+00:00',
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    expect(await screen.findByText('Incomplete')).toBeInTheDocument()
    expect(screen.getByText(/resolve by/i)).toBeInTheDocument()
    // The "Earned credit" column header is always present; the row itself carries neither an
    // earned nor a not-earned cell — an Incomplete is undecided, not a graded outcome.
    expect(
      screen.queryByRole('cell', { name: /^(earned credit|did not earn credit)$/i }),
    ).not.toBeInTheDocument()
  })

  it('shows "no deadline yet" for an Incomplete whose next semester does not exist', async () => {
    payload = {
      data: {
        cgpa: null,
        semesters: [
          semester({
            gpa: null,
            courses: [
              {
                enrollment_id: 9,
                course,
                credit_hours: 3,
                course_status: 'Incomplete',
                grade: null,
                earned: null,
                superseded: false,
                incomplete_deadline: null,
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    expect(await screen.findByText(/resolution deadline not yet set/i)).toBeInTheDocument()
  })

  it('shows a Fail Withdrawal with no letter and no deadline caption', async () => {
    payload = {
      data: {
        cgpa: null,
        semesters: [
          semester({
            gpa: null,
            courses: [
              {
                enrollment_id: 9,
                course,
                credit_hours: 3,
                course_status: 'FailWithdrawal',
                grade: null,
                earned: null,
                superseded: false,
                incomplete_deadline: null,
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    expect(await screen.findByText('Fail Withdrawal')).toBeInTheDocument()
    expect(screen.queryByText(/deadline/i)).not.toBeInTheDocument()
  })

  it('shows a below-C+ letter with the sub-earn emphasis and "did not earn credit"', async () => {
    payload = {
      data: {
        cgpa: 1.0,
        semesters: [
          semester({
            gpa: 1.0,
            courses: [
              {
                enrollment_id: 6,
                course,
                credit_hours: 3,
                grade: { mark: 62, letter_grade: 'D', gpa_points: 1.0 },
                earned: false,
                superseded: false,
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    expect(await screen.findByText('D')).toBeInTheDocument()
    expect(screen.getByText(/did not earn credit/i)).toBeInTheDocument()
  })

  it('shows earlier corrected grades as visible history beside the current grade', async () => {
    payload = {
      data: {
        cgpa: 4.0,
        semesters: [
          semester({
            gpa: 4.0,
            courses: [
              {
                enrollment_id: 5,
                course,
                credit_hours: 3,
                grade: { mark: 95, letter_grade: 'A+', gpa_points: 4.0 },
                grade_history: [
                  { mark: 82.5, letter_grade: 'B', gpa_points: 3.0, superseded: true },
                ],
                earned: true,
                superseded: false,
              },
            ],
          }),
        ],
      },
    }
    renderPage()

    expect(
      await screen.findByText(/earlier grades: b \(82\.50%, superseded\)/i),
    ).toBeInTheDocument()
  })
})
