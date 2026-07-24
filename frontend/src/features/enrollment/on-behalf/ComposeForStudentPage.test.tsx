import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ComposeForStudentPage } from './ComposeForStudentPage'

// The endpoint bodies the page reads, behind the shared Axios mutator, so the page drives the
// REAL generated hooks (picker → pick → target eligible list → cart → compose). Dispatched by
// URL and method.
const srv = vi.hoisted(() => ({
  students: undefined as unknown,
  eligible: undefined as unknown,
  composed: [] as Array<{ url: string; body: unknown }>,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()
    if (url.endsWith('/on-behalf/students')) {
      return Promise.resolve(srv.students)
    }
    if (url.includes('/eligible-courses')) {
      return Promise.resolve(srv.eligible)
    }
    if (url.endsWith('/requests') && method === 'post') {
      srv.composed.push({ url, body: config.data })
      return Promise.resolve({ data: { id: 1, status: 'Approved' } })
    }
    return Promise.resolve({ data: {} }) // `/me` and anything else
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <ComposeForStudentPage />
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

const semester = {
  id: 1,
  term: 'Fall',
  year: 2026,
  name_en: 'Fall 2026',
  name_ar: 'خريف 2026',
  registration_closes_at: '2026-09-05T00:00:00+00:00',
  is_registration_open: true,
}

const eligibleCourse = {
  offering_id: 1,
  classification: 'Core',
  capacity: 30,
  seats_remaining: 30,
  total_ch: 3,
  course: {
    id: 1,
    code: 'DEP501',
    name_en: 'Advanced Algorithms',
    name_ar: 'خوارزميات',
    level: 500,
  },
  instructor: { id: 4, name: 'Dr. Instructor', email: 'i@ejust.test' },
  meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
}

beforeEach(() => {
  srv.students = { data: [student] }
  srv.eligible = { data: { semester, courses: [eligibleCourse] } }
  srv.composed = []
})

describe('ComposeForStudentPage', () => {
  it('picks a student, builds a cart from their eligible list, and composes it', async () => {
    const user = userEvent.setup()
    renderPage()

    // Pick the student from the searchable picker.
    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    // The target's context + eligible list appear (the student's first semester waives the floor).
    expect(await screen.findByText(/registering for fall 2026/i)).toBeInTheDocument()
    await user.click(await screen.findByRole('button', { name: /add dep501 to your cart/i }))

    // Submit composes on the target's behalf.
    const submit = screen.getByRole('button', { name: /submit registration/i })
    expect(submit).toBeEnabled()
    await user.click(submit)

    // The mock returns an Approved outcome (advisor composing for their advisee), so the
    // confirmation says the student was enrolled immediately.
    expect(await screen.findByText(/sara student was enrolled immediately/i)).toBeInTheDocument()
    expect(srv.composed).toHaveLength(1)
    expect(srv.composed[0].url).toContain('/on-behalf/students/5/requests')
  }, 20000)

  it('tells the composer when no registration window is open for the picked student', async () => {
    srv.eligible = { data: { semester: null, courses: [] } }
    const user = userEvent.setup()
    renderPage()

    const picker = await screen.findByRole('combobox', { name: /choose a student/i })
    await user.click(picker)
    await user.click(await screen.findByRole('option', { name: /Sara Student/i }))

    expect(await screen.findByText(/registration is not open right now/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /submit registration/i })).not.toBeInTheDocument()
  }, 20000)
})
