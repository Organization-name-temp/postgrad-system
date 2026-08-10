import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { GradeSheetsPage } from './GradeSheetsPage'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (as the app always does).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

// The offerings GET behind the shared Axios mutator, so the page drives the real generated hook.
// One screen serves two personas and the server says which by `meta.scope` (ticket 09), so the
// payload is what each test varies — never a mocked permission check in the component.
const srv = vi.hoisted(() => ({ offerings: undefined as unknown }))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    if ((config.url ?? '').endsWith('/grading/offerings')) {
      return Promise.resolve(srv.offerings)
    }
    return Promise.resolve({ data: {} })
  },
}))

function offeringsPayload(scope: string) {
  return {
    data: [
      {
        offering_id: 3,
        gradable_count: 12,
        sheet_status: 'Draft',
        course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ', level: 500 },
        semester: { id: 2, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
        instructor: { id: 4, name: 'Nadia Instructor' },
      },
    ],
    meta: { scope },
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <GradeSheetsPage />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

describe('GradeSheetsPage', () => {
  it("reads as the instructor's own courses, without an instructor column", async () => {
    srv.offerings = offeringsPayload('own')
    renderPage()

    // Wait for the loaded row: the heading renders before the fetch resolves, so asserting it
    // first would pass against the still-empty screen.
    expect(await screen.findByText('Fall 2026')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Grade sheets' })).toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Instructor' })).not.toBeInTheDocument()
    expect(screen.queryByText('Nadia Instructor')).not.toBeInTheDocument()
  })

  it('reads as central entry, naming whose offering each row is (ticket 09)', async () => {
    srv.offerings = offeringsPayload('central')
    renderPage()

    expect(await screen.findByText('Fall 2026')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Central grade entry' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Instructor' })).toBeInTheDocument()
    expect(screen.getByText('Nadia Instructor')).toBeInTheDocument()
    expect(screen.getByText(/recorded in the audit log/i)).toBeInTheDocument()
  })

  it('says nothing exists at all, rather than "you teach nothing", on the central picker', async () => {
    srv.offerings = { data: [], meta: { scope: 'central' } }
    renderPage()

    expect(await screen.findByText('No course offerings exist yet.')).toBeInTheDocument()
  })
})
