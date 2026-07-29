import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { vi } from 'vitest'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { OutstandingIncompletesPage } from './OutstandingIncompletesPage'

let payload: unknown

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/grading/incompletes')) {
      return Promise.resolve(payload)
    }
    return Promise.resolve({ data: {} })
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <OutstandingIncompletesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('OutstandingIncompletesPage (ticket 05)', () => {
  it('shows the empty message when there are none', async () => {
    payload = { data: [] }
    renderPage()

    expect(await screen.findByText(/no outstanding incompletes/i)).toBeInTheDocument()
  })

  it('lists an outstanding Incomplete with its deadline and a resolve link', async () => {
    payload = {
      data: [
        {
          incomplete_id: 9,
          enrollment_id: 5,
          course_offering_id: 3,
          course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ' },
          student: { student_id: 7, university_id: '2026-MSC-CE-0001', name: 'Sara Student' },
          completion_percentage: 80,
          reason: 'Hospitalised before the final project was due.',
          deadline: '2026-11-03T00:00:00+00:00',
        },
      ],
    }
    renderPage()

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText('DEP501')).toBeInTheDocument()
    expect(screen.getByText(/80% completed/i)).toBeInTheDocument()
    expect(screen.getByText(/hospitalised/i)).toBeInTheDocument()
    expect(screen.getByText(/deadline:/i)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /resolve/i })).toHaveAttribute(
      'href',
      '/grading/incompletes/9',
    )
  })

  it('shows "no deadline yet" when the incomplete has none', async () => {
    payload = {
      data: [
        {
          incomplete_id: 9,
          enrollment_id: 5,
          course_offering_id: 3,
          course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ' },
          student: { student_id: 7, university_id: '2026-MSC-CE-0001', name: 'Sara Student' },
          completion_percentage: 80,
          reason: 'Hospitalised before the final project was due.',
          deadline: null,
        },
      ],
    }
    renderPage()

    expect(await screen.findByText(/no deadline yet/i)).toBeInTheDocument()
  })
})
