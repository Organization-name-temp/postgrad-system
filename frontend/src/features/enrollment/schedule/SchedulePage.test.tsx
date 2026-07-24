import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { SchedulePage } from './SchedulePage'

// The schedule read behind the shared Axios mutator, so the page drives the REAL generated
// query hook (fetch → group → render → period-gated actions). Everything else resolves empty.
const srv = vi.hoisted(() => ({ schedule: undefined as unknown }))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/registration/schedule')) {
      return Promise.resolve(srv.schedule)
    }
    return Promise.resolve({ data: {} }) // `/me` and anything else
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <SchedulePage />
    </QueryClientProvider>,
  )
}

const semester = {
  id: 1,
  term: 'Fall',
  year: 2026,
  name_en: 'Fall 2026',
  name_ar: 'خريف 2026',
  is_registration_open: false,
}

function enrollment(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    offering_id: 1,
    course_status: 'Regular',
    classification: 'Core',
    credit_hours: 3,
    course: {
      id: 1,
      code: 'DEP501',
      name_en: 'Advanced Algorithms',
      name_ar: 'خوارزميات',
      level: 500,
    },
    instructor: { id: 4, name: 'Dr. Instructor', email: 'i@ejust.test' },
    meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
    ...overrides,
  }
}

function group(overrides: Record<string, unknown>) {
  return {
    semester,
    current_period: 'closed',
    can_free_change: false,
    can_withdraw: false,
    addable_courses: [],
    enrollments: [enrollment()],
    ...overrides,
  }
}

beforeEach(() => {
  srv.schedule = { data: [] }
})

describe('SchedulePage period-gated actions', () => {
  it('shows the empty state when there are no enrollments', async () => {
    renderPage()
    expect(await screen.findByText(/no enrollments yet/i)).toBeInTheDocument()
  })

  it('in the add/drop period shows the drop button and the add panel, not withdraw', async () => {
    srv.schedule = {
      data: [
        group({
          current_period: 'add_drop',
          can_free_change: true,
          addable_courses: [
            {
              offering_id: 2,
              classification: 'Core',
              total_ch: 3,
              course: {
                id: 2,
                code: 'DEP502',
                name_en: 'Distributed Systems',
                name_ar: 'ب',
                level: 500,
              },
              instructor: { id: 4, name: 'Dr. Instructor', email: 'i@ejust.test' },
              meetings: [{ weekday: 3, starts_at: '13:00', ends_at: '15:00' }],
            },
          ],
        }),
      ],
    }
    renderPage()

    // The current-period banner tells the student what is allowed now.
    expect(await screen.findByText(/add or drop courses directly/i)).toBeInTheDocument()
    // Drop is offered in context; withdraw is not (wrong window).
    expect(screen.getByRole('button', { name: /drop dep501/i })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /withdraw from dep501/i })).not.toBeInTheDocument()
    // The add-a-course panel is present with the addable offering.
    expect(screen.getByRole('heading', { name: /add a course/i })).toBeInTheDocument()
    expect(screen.getByText('DEP502')).toBeInTheDocument()
  })

  it('in the withdrawal period shows the withdraw button behind a confirmation, not drop or add', async () => {
    srv.schedule = { data: [group({ current_period: 'withdrawal', can_withdraw: true })] }
    renderPage()

    expect(await screen.findByText(/withdrawal period/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /drop dep501/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /add a course/i })).not.toBeInTheDocument()

    // Withdrawing is self-serve but requires a confirmation.
    fireEvent.click(screen.getByRole('button', { name: /withdraw from dep501/i }))
    expect(await screen.findByText(/records a permanent w/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /^confirm$/i })).toBeInTheDocument()
  })

  it('outside any change window is read-only with no action buttons', async () => {
    srv.schedule = { data: [group({ current_period: 'closed' })] }
    renderPage()

    expect(await screen.findByText(/no changes are open/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /drop dep501/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /withdraw from dep501/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /add a course/i })).not.toBeInTheDocument()
  })
})
