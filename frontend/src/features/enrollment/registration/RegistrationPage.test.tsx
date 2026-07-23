import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { RegistrationPage } from './RegistrationPage'

// The eligible-list body the endpoint returns, behind the shared Axios mutator, so the
// page drives the REAL generated query hook (fetch → group → render), not a stub.
const srv = vi.hoisted(() => ({
  body: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/registration/eligible-courses')) {
      return Promise.resolve(srv.body)
    }
    return Promise.resolve({ data: {} })
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

describe('RegistrationPage', () => {
  beforeEach(() => {
    srv.body = undefined
  })

  it('tells the student when no registration window is open', async () => {
    srv.body = { data: { semester: null, courses: [] } }
    renderPage()

    expect(await screen.findByText(/registration is not open/i)).toBeInTheDocument()
  })

  it('shows the open semester and until-when registration is open', async () => {
    srv.body = { data: { semester, courses: [] } }
    renderPage()

    expect(await screen.findByText(/registering for fall 2026/i)).toBeInTheDocument()
    expect(screen.getByText(/registration is open until/i)).toBeInTheDocument()
    // Semester present but nothing eligible yet — the empty message, not "not open".
    expect(screen.getByText(/no courses available/i)).toBeInTheDocument()
  })

  it('groups eligible courses under classification headings in presentation order', async () => {
    srv.body = {
      data: {
        semester,
        courses: [
          eligibleLine({ offering_id: 10, classification: 'ResearchCourse' }),
          eligibleLine({ offering_id: 11, classification: 'Preparatory' }),
          eligibleLine({ offering_id: 12, classification: 'ExternalElective' }),
        ],
      },
    }
    renderPage()

    const headings = await screen.findAllByRole('heading', { level: 2 })
    expect(headings.map((h) => h.textContent)).toEqual([
      expect.stringMatching(/preparatory courses/i),
      expect.stringMatching(/external electives/i),
      expect.stringMatching(/research course/i),
    ])
  })

  it('labels external electives with their advisor-approval rule', async () => {
    srv.body = {
      data: { semester, courses: [eligibleLine({ classification: 'ExternalElective' })] },
    }
    renderPage()

    expect(await screen.findByText(/you may use one external elective/i)).toBeInTheDocument()
  })

  it('renders each line with its instructor, meeting time, seats and credit hours', async () => {
    srv.body = { data: { semester, courses: [eligibleLine({})] } }
    renderPage()

    const row = (await screen.findByText('DEP501')).closest('tr') as HTMLElement
    expect(within(row).getByText('Dr. Instructor')).toBeInTheDocument()
    expect(within(row).getByText(/monday 09:00–11:00/i)).toBeInTheDocument()
    expect(within(row).getByText('30')).toBeInTheDocument() // seats remaining
    expect(within(row).getByText('3')).toBeInTheDocument() // credit hours
  })

  it('shows "no limit" for an uncapped offering', async () => {
    srv.body = {
      data: {
        semester,
        courses: [eligibleLine({ capacity: null, seats_remaining: null })],
      },
    }
    renderPage()

    const row = (await screen.findByText('DEP501')).closest('tr') as HTMLElement
    expect(within(row).getByText(/no limit/i)).toBeInTheDocument()
  })
})
