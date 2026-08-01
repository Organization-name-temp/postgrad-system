import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { StudentSchedulePage } from './StudentSchedulePage'

// The endpoint bodies the page reads, behind the shared Axios mutator, so the page drives the
// REAL generated hooks (picker → pick → oversight schedule read). Dispatched by URL.
const srv = vi.hoisted(() => ({
  students: undefined as unknown,
  schedule: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/on-behalf/students')) {
      return Promise.resolve(srv.students)
    }
    if (url.endsWith('/schedule')) {
      return Promise.resolve(srv.schedule)
    }
    return Promise.resolve({ data: {} }) // `/me` and anything else
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <StudentSchedulePage />
    </QueryClientProvider>,
  )
}

const student = {
  student_id: 5,
  name: 'Sara Student',
  university_id: '2026-MSC-CE-0001',
  enrollment_term: 'Fall 2026',
  degree_level: 'MSc',
  program: {
    id: 1,
    code: 'MSC-CE',
    name_en: 'MSc in Computer Engineering',
    name_ar: 'ماجستير',
    degree_level: 'MSc',
  },
}

const scheduleGroup = {
  semester: { id: 1, term: 'Fall', year: 2026, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
  current_period: 'add_drop',
  can_free_change: false,
  can_withdraw: false,
  addable_courses: [],
  enrollments: [
    {
      id: 1,
      offering_id: 1,
      course_status: 'Regular',
      classification: 'Core',
      credit_hours: 3,
      must_retake: false,
      course: {
        id: 1,
        code: 'DEP501',
        name_en: 'Advanced Algorithms',
        name_ar: 'خوارزميات',
        level: 500,
      },
      instructor: { id: 4, name: 'Dr. Instructor', email: 'i@ejust.test' },
      meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      attendance: { present: 8, recorded: 10, percentage: 80, at_risk: false },
    },
  ],
}

beforeEach(() => {
  srv.students = { data: [student] }
  srv.schedule = { data: [scheduleGroup] }
})

describe('StudentSchedulePage', () => {
  it('picks a student and renders their schedule read-only, with attendance', async () => {
    const user = userEvent.setup()
    renderPage()

    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    // The picked student's schedule appears — course, semester and derived attendance.
    expect(await screen.findByText('DEP501')).toBeInTheDocument()
    expect(screen.getByText('Fall 2026')).toBeInTheDocument()
    expect(screen.getByText('80%')).toBeInTheDocument()
    // Read-only: no drop / withdraw actions on the oversight view.
    expect(screen.queryByRole('button', { name: /drop|withdraw/i })).not.toBeInTheDocument()
  }, 20000)

  it('tells the reviewer when the picked student has no enrollments', async () => {
    srv.schedule = { data: [] }
    const user = userEvent.setup()
    renderPage()

    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    expect(await screen.findByText(/no enrollments yet/i)).toBeInTheDocument()
  }, 20000)

  it('shows the must-retake marker on oversight schedule rows', async () => {
    srv.schedule = {
      data: [
        {
          ...scheduleGroup,
          enrollments: [{ ...scheduleGroup.enrollments[0], must_retake: true }],
        },
      ],
    }
    const user = userEvent.setup()
    renderPage()

    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    expect(await screen.findByText(/must retake/i)).toBeInTheDocument()
  }, 20000)
})
