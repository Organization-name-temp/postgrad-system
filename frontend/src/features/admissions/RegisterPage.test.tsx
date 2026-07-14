import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../shared/i18n/config' // initialise i18next so useTranslation works
import { RegisterPage } from './RegisterPage'

interface OpenCycle {
  id: number
  name_en: string
  name_ar: string
}

// A tiny in-memory server behind the shared Axios mutator, so the page drives the
// REAL generated hooks (open-cycle query + register mutation): the open-cycle
// gating and the submit → session → navigate loop are observed, not stubbed.
const srv = vi.hoisted(() => ({
  openCycles: [] as OpenCycle[],
  registerBody: undefined as unknown,
  authed: false,
}))

vi.mock('../../shared/api/http', () => ({
  // i18n/config imports `http` to set Accept-Language, and `register` primes the
  // CSRF cookie via `http.get` — provide both so replacing the module is safe.
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/admission-cycles/open')) {
      return Promise.resolve({ data: srv.openCycles })
    }
    if (url.endsWith('/register')) {
      srv.registerBody = config.data
      srv.authed = true // registering establishes the Sanctum session
      return Promise.resolve({ data: { id: 1, status: 'draft', admission_cycle_id: 1 } })
    }
    // The `/me` probe behind useAuth: 401 until registration establishes a session,
    // then the authenticated applicant (as real Sanctum behaves).
    if (url.endsWith('/me')) {
      return srv.authed
        ? Promise.resolve({
            data: {
              id: 1,
              name: 'Sara Applicant',
              email: 'sara@example.test',
              roles: [{ code: 'R01', name: 'Applicant' }],
              permissions: [],
            },
          })
        : Promise.reject(new Error('unauthenticated'))
    }
    return Promise.resolve({})
  },
}))

function renderRegister() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/register']}>
        <Routes>
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/login" element={<div>Login screen</div>} />
          <Route path="/apply" element={<div>Application wizard</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('RegisterPage', () => {
  beforeEach(() => {
    srv.openCycles = []
    srv.registerBody = undefined
    srv.authed = false
  })

  it('shows an admissions-closed state when no cycle is open', async () => {
    renderRegister()

    expect(await screen.findByText(/admissions are currently closed/i)).toBeInTheDocument()
    expect(screen.queryByLabelText(/full name/i)).not.toBeInTheDocument()
  })

  it('shows the form without a cycle picker when exactly one cycle is open', async () => {
    srv.openCycles = [{ id: 7, name_en: 'Fall 2026', name_ar: 'خريف 2026' }]
    renderRegister()

    expect(await screen.findByLabelText(/full name/i)).toBeInTheDocument()
    expect(screen.queryByLabelText(/admission cycle/i)).not.toBeInTheDocument()
  })

  it('shows a cycle picker when more than one cycle is open', async () => {
    srv.openCycles = [
      { id: 7, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
      { id: 8, name_en: 'Spring 2027', name_ar: 'ربيع 2027' },
    ]
    renderRegister()

    expect(await screen.findByLabelText(/admission cycle/i)).toBeInTheDocument()
  })

  it('registers and lands the applicant in their application wizard', async () => {
    srv.openCycles = [{ id: 7, name_en: 'Fall 2026', name_ar: 'خريف 2026' }]
    // delay: null removes the inter-keystroke wait — this fills four fields and
    // then awaits a mutation + /me refetch + navigation, which is slow enough to
    // race the default timeout when the whole suite runs in parallel.
    const user = userEvent.setup({ delay: null })
    renderRegister()

    await user.type(await screen.findByLabelText(/full name/i), 'Sara Applicant')
    await user.type(screen.getByLabelText(/email/i), 'sara@example.test')
    await user.type(screen.getByLabelText(/^password/i), 'correct-horse')
    await user.type(screen.getByLabelText(/confirm password/i), 'correct-horse')

    await user.click(screen.getByRole('button', { name: /register and start/i }))

    // Navigated into the application wizard, and the server got the details (no
    // admission_cycle_id — the single open Cycle is chosen server-side).
    expect(await screen.findByText('Application wizard')).toBeInTheDocument()
    expect(srv.registerBody).toEqual({
      name: 'Sara Applicant',
      email: 'sara@example.test',
      password: 'correct-horse',
      password_confirmation: 'correct-horse',
    })
  }, 15000)
})
