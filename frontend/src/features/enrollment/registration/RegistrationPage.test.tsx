import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { RegistrationPage } from './RegistrationPage'

// The endpoint bodies the page reads, behind the shared Axios mutator, so the page drives
// the REAL generated query hooks (fetch → group → render → cart). Dispatched by URL and
// method: the eligible list, the own-requests read, `/me`, and the submit POST.
const srv = vi.hoisted(() => ({
  eligible: undefined as unknown,
  requests: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()
    if (url.endsWith('/registration/eligible-courses')) {
      return Promise.resolve(srv.eligible)
    }
    if (url.endsWith('/registration/requests') && method === 'get') {
      return Promise.resolve(srv.requests)
    }
    if (url.endsWith('/registration/requests') && method === 'post') {
      return Promise.resolve({ data: {} })
    }
    return Promise.resolve({ data: {} }) // `/me` and anything else
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <RegistrationPage />
    </QueryClientProvider>,
  )
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

function eligibleLine(overrides: Record<string, unknown>) {
  return {
    offering_id: 1,
    classification: 'Core',
    capacity: 30,
    seats_remaining: 30,
    total_ch: 3,
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
    ...overrides,
  }
}

/** The cart landmark section, so course lookups are scoped away from the eligible list. */
function cartRegion() {
  return screen.getByRole('region', { name: /your registration/i })
}

beforeEach(() => {
  srv.eligible = { data: { semester, courses: [] } }
  srv.requests = { data: { semester, requests: [] } }
})

describe('RegistrationPage', () => {
  it('tells the student when no registration window is open', async () => {
    srv.eligible = { data: { semester: null, courses: [] } }
    srv.requests = { data: { semester: null, requests: [] } }
    renderPage()

    expect(await screen.findByText(/registration is not open/i)).toBeInTheDocument()
  })

  it('shows the open semester and groups eligible courses under classification headings', async () => {
    srv.eligible = {
      data: {
        semester,
        courses: [
          eligibleLine({ offering_id: 10, classification: 'ResearchCourse' }),
          eligibleLine({ offering_id: 11, classification: 'Preparatory' }),
        ],
      },
    }
    renderPage()

    expect(await screen.findByText(/registering for fall 2026/i)).toBeInTheDocument()
    // The eligible list groups (h2 landmarks) plus the cart landmark are present.
    expect(screen.getByRole('heading', { name: /preparatory courses/i })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /research course/i })).toBeInTheDocument()
    expect(cartRegion()).toBeInTheDocument()
  })

  it('starts with an empty cart and a disabled submit', async () => {
    srv.eligible = { data: { semester, courses: [eligibleLine({})] } }
    renderPage()

    await screen.findByText('DEP501') // eligible list rendered
    expect(within(cartRegion()).getByText(/your cart is empty/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /submit to advisor/i })).toBeDisabled()
  })

  it('adds a course to the cart, sums its credit hours, and warns below the minimum', async () => {
    srv.eligible = { data: { semester, courses: [eligibleLine({})] } }
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /add dep501 to your cart/i }))

    const cart = cartRegion()
    expect(within(cart).getByText('DEP501')).toBeInTheDocument()
    expect(within(cart).getByText(/total: 3 credit hours/i)).toBeInTheDocument()
    // 3 CH is below the 6-hour floor, so submit stays disabled with a warning.
    expect(within(cart).getByText(/below the 6-hour minimum/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /submit to advisor/i })).toBeDisabled()
  })

  it('enables submit once the cart reaches the credit-hour floor', async () => {
    srv.eligible = {
      data: {
        semester,
        courses: [
          eligibleLine({
            offering_id: 1,
            course: { id: 1, code: 'DEP501', name_en: 'A', name_ar: 'أ', level: 500 },
          }),
          eligibleLine({
            offering_id: 2,
            course: { id: 2, code: 'DEP502', name_en: 'B', name_ar: 'ب', level: 500 },
            meetings: [{ weekday: 2, starts_at: '09:00', ends_at: '11:00' }],
          }),
        ],
      },
    }
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /add dep501 to your cart/i }))
    fireEvent.click(screen.getByRole('button', { name: /add dep502 to your cart/i }))

    const cart = cartRegion()
    expect(within(cart).getByText(/total: 6 credit hours/i)).toBeInTheDocument()
    expect(within(cart).queryByText(/below the 6-hour minimum/i)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /submit to advisor/i })).toBeEnabled()
  })

  it('shows the submitted request view when a request is pending', async () => {
    srv.requests = {
      data: {
        semester,
        requests: [
          {
            id: 7,
            semester_id: 1,
            kind: 'Initial',
            status: 'Pending',
            advisor_note: null,
            submitted_at: '2026-08-02T09:00:00+00:00',
            lines: [
              {
                offering_id: 1,
                mode: 'Regular',
                classification: 'Core',
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
              },
            ],
          },
        ],
      },
    }
    renderPage()

    expect(
      await screen.findByRole('heading', { name: /your submitted registration/i }),
    ).toBeInTheDocument()
    expect(screen.getByText(/pending advisor review/i)).toBeInTheDocument()
    expect(screen.getByText('DEP501')).toBeInTheDocument()
    // The cart builder is not shown while a request is pending.
    expect(screen.queryByRole('button', { name: /submit to advisor/i })).not.toBeInTheDocument()
  })

  it('shows the must-retake marker on eligible courses', async () => {
    srv.eligible = { data: { semester, courses: [eligibleLine({ must_retake: true })] } }
    renderPage()

    expect(await screen.findByText(/must retake/i)).toBeInTheDocument()
  })
})
