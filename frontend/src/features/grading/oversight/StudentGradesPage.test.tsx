import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config'
import { StudentGradesPage } from './StudentGradesPage'

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

const srv = vi.hoisted(() => ({
  students: undefined as unknown,
  record: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url === '/api/v1/grading/students') {
      return Promise.resolve(srv.students)
    }
    if (url === '/api/v1/grading/students/5') {
      return Promise.resolve(srv.record)
    }
    return Promise.resolve({ data: {} })
  },
}))

function renderPage(entry = '/grading/students') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[entry]}>
          <Routes>
            <Route path="/grading/students" element={<StudentGradesPage />} />
            <Route path="/grading/students/:studentId" element={<StudentGradesPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

const student = {
  student_id: 5,
  name: 'Sara Student',
  university_id: '2026-MSC-CE-0001',
  enrollment_term: 'Fall 2026',
  degree_level: 'MSc',
  current_load: null,
  program: {
    id: 1,
    code: 'MSC-CE',
    name_en: 'MSc in Computer Engineering',
    name_ar: 'ماجستير هندسة الحاسوب',
    degree_level: 'MSc',
  },
}

beforeEach(() => {
  srv.students = { data: [student] }
  srv.record = {
    data: {
      cgpa: 1.0,
      semesters: [
        {
          semester: {
            id: 2,
            term: 'Fall',
            year: 2026,
            name_en: 'Fall 2026',
            name_ar: 'خريف 2026',
          },
          gpa: 1.0,
          courses: [
            {
              enrollment_id: 5,
              credit_hours: 3,
              course_status: 'Retake',
              must_retake: true,
              superseded: false,
              earned: false,
              course: {
                id: 1,
                code: 'DEP501',
                name_en: 'Advanced Algorithms',
                name_ar: 'خوارزميات متقدمة',
                level: 500,
              },
              grade: { mark: 62, letter_grade: 'D', gpa_points: 1.0 },
              grade_history: [],
            },
          ],
        },
      ],
    },
  }
})

describe('StudentGradesPage', () => {
  it('uses the shared picker and loads a scoped student record', async () => {
    const user = userEvent.setup()
    renderPage()

    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    expect(await screen.findByText('DEP501')).toBeInTheDocument()
    expect(screen.getByText('1.00')).toBeInTheDocument()
    expect(screen.getByText(/must retake/i)).toBeInTheDocument()
  }, 20000)

  it('hydrates the selected student from the route parameter for deep links from advising', async () => {
    renderPage('/grading/students/5')

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText(/2026-MSC-CE-0001/)).toBeInTheDocument()
    expect(await screen.findByText('DEP501')).toBeInTheDocument()
  })
})
