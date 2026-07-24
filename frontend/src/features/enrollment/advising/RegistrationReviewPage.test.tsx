import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { RegistrationReviewPage } from './RegistrationReviewPage'

// The review GET and the decision POST behind the shared Axios mutator, so the page drives
// the REAL generated hooks (fetch → render → decide). `srv.review` is the payload; every
// decision POST body is captured for assertion.
const srv = vi.hoisted(() => ({
  review: undefined as unknown,
  posted: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/decision')) {
      srv.posted = config.data
      return Promise.resolve({ data: {} })
    }
    if (url.endsWith('/registration/requests/1')) {
      return Promise.resolve(srv.review)
    }
    return Promise.resolve({ data: {} })
  },
}))

function line(overrides: Record<string, unknown>) {
  return {
    id: 1,
    offering_id: 1,
    mode: 'Regular',
    decision: null,
    derived_retake: false,
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
    ...overrides,
  }
}

function reviewPayload(lines: Record<string, unknown>[], status = 'Pending') {
  return {
    data: {
      request: { id: 1, semester_id: 1, kind: 'Initial', status, advisor_note: null, lines },
      student: {
        student_id: 5,
        name: 'Sara Student',
        university_id: '2026-MSC-CE-0001',
        enrollment_term: 'Fall 2026',
        degree_level: 'MSc',
        current_load: 0,
        program: { id: 1, code: 'MSC-CE', name_en: 'CE', name_ar: 'هـ', degree_level: 'MSc' },
      },
    },
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/advising/1']}>
        <Routes>
          <Route path="/advising/:requestId" element={<RegistrationReviewPage />} />
          <Route path="/advising" element={<div>queue</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  srv.review = reviewPayload([line({})])
  srv.posted = undefined
})

describe('RegistrationReviewPage', () => {
  it('renders the student context and the requested lines', async () => {
    srv.review = reviewPayload([line({ id: 1, derived_retake: true })])
    renderPage()

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText('2026-MSC-CE-0001')).toBeInTheDocument()
    expect(screen.getByText('DEP501')).toBeInTheDocument()
    // The derived-Retake label shows for a line that would mint as a retake.
    expect(screen.getByText(/retake/i)).toBeInTheDocument()
  })

  it('posts every line approved by default', async () => {
    srv.review = reviewPayload([line({ id: 1 }), line({ id: 2, offering_id: 2 })])
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /record decision/i }))

    await waitFor(() => expect(srv.posted).toBeTruthy())
    expect(srv.posted).toMatchObject({
      lines: [
        { id: 1, decision: 'approved' },
        { id: 2, decision: 'approved' },
      ],
    })
  })

  it('records a rejected line when the advisor trims it', async () => {
    srv.review = reviewPayload([line({ id: 1 })])
    renderPage()

    await screen.findByText('DEP501')
    // Open the line's decision select and choose Reject.
    fireEvent.mouseDown(screen.getByRole('combobox'))
    const listbox = within(screen.getByRole('listbox'))
    fireEvent.click(listbox.getByText(/^reject$/i))

    fireEvent.click(screen.getByRole('button', { name: /record decision/i }))

    await waitFor(() => expect(srv.posted).toBeTruthy())
    expect(srv.posted).toMatchObject({ lines: [{ id: 1, decision: 'rejected' }] })
  })
})
