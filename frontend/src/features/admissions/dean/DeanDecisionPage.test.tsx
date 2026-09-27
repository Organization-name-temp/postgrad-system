import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { DeanDecisionPage } from './DeanDecisionPage'

// A tiny in-memory server behind the shared mutator so the decision form drives the
// REAL generated hooks: the application detail + timeline + advisor reads and the
// decide POST are observed end-to-end (its action + advisor body), not stubbed.
const calls = vi.hoisted(() => ({
  noAdvisor: false,
  list: [] as Array<{ method?: string; url?: string; data?: unknown }>,
}))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: AxiosRequestConfig) => {
    calls.list.push({ method: config.method, url: config.url, data: config.data })
    // customInstance resolves to the response body (Orval's mutator unwraps axios).
    const url = config.url ?? ''
    if (url.endsWith('/timeline')) {
      return Promise.resolve({ data: [] })
    }
    if (url.endsWith('/dean/advisors')) {
      if (calls.noAdvisor)
        return Promise.resolve({ data: [], meta: { total: 0, suggested_advisor_id: null } })
      return Promise.resolve({
        data: [
          { id: 3, name: 'Dr. Busy', email: 'busy@ejust.test' },
          { id: 4, name: 'Dr. Free', email: 'free@ejust.test' },
        ],
        meta: { suggested_advisor_id: 4 },
      })
    }
    if (url.endsWith('/decide')) {
      return Promise.resolve({
        data: { id: 1, status: 'accepted' },
        meta: { university_id: '2026-MSC-CE-0001' },
      })
    }
    // The application detail (GET /applications/1).
    return Promise.resolve({
      data: {
        id: 1,
        status: 'interview_completed',
        name_en: 'Sara Applicant',
        national_id: '29001010101010',
        applying_for_degree: 'MSc',
        primary_program: { id: 10, code: 'MSC-CE', name_en: 'MSc in Computer Engineering' },
        documents: [],
      },
    })
  },
}))

// StatusChip reads CSS-variable palette entries, so render under a cssVariables theme
// (as the app always does).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <MemoryRouter initialEntries={['/dean/decisions/1']}>
          <Routes>
            <Route path="/dean/decisions/:applicationId" element={<DeanDecisionPage />} />
            <Route path="/dean/decisions" element={<div>decisions queue</div>} />
          </Routes>
        </MemoryRouter>
      </ThemeProvider>
    </QueryClientProvider>,
  )
}

describe('DeanDecisionPage', () => {
  beforeEach(() => {
    calls.list = []
    calls.noAdvisor = false
  })

  // These drive the real generated hooks (detail + advisor reads, then a mutation) and
  // are userEvent-paced, so they get headroom beyond the 5s default — the full Vitest
  // run is import-bound under parallel load on this machine (see dev notes), and the
  // slack keeps that contention from flaking the gate.
  it('accepts with the pre-filled advisor and shows the minted University ID', async () => {
    const user = userEvent.setup()
    renderPage()

    // The applicant loads, and the least-loaded advisor (Dr. Free, id 4) is pre-selected.
    await screen.findByRole('heading', { name: 'Sara Applicant' })
    expect(await screen.findByText(/Dr. Free/)).toBeInTheDocument()

    // Accept enables only once the suggested advisor has pre-filled (never posts null).
    const accept = screen.getByRole('button', { name: /accept & admit/i })
    await waitFor(() => expect(accept).toBeEnabled())
    await user.click(accept)

    // The decide POST carried action=accept with the suggested advisor id.
    await waitFor(() =>
      expect(calls.list).toContainEqual(
        expect.objectContaining({ method: 'POST', url: '/api/v1/dean/applications/1/decide' }),
      ),
    )
    const post = calls.list.find((c) => c.method === 'POST')
    expect(post?.data).toMatchObject({ action: 'accept', advisor_id: 4 })

    // The minted University ID is surfaced.
    expect(await screen.findByText(/2026-MSC-CE-0001/)).toBeInTheDocument()
  }, 20_000)

  it('keeps acceptance disabled when the owner returns no suggested advisor', async () => {
    calls.noAdvisor = true
    renderPage()
    await screen.findByText('No academic advisor is available to assign.')
    expect(screen.getByRole('button', { name: /accept & admit/i })).toBeDisabled()
  })

  it('requires a reason before a reject can be confirmed', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /^reject$/i }))
    const confirm = screen.getByRole('button', { name: /confirm decision/i })
    expect(confirm).toBeDisabled()

    await user.type(screen.getByLabelText(/reason/i), 'Not recommended.')
    expect(confirm).toBeEnabled()
  }, 20_000)
})
