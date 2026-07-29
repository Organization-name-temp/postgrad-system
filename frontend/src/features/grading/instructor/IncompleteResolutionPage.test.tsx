import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { IncompleteResolutionPage } from './IncompleteResolutionPage'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (the GradeSheetPage test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

const srv = vi.hoisted(() => ({
  round: undefined as unknown,
  signed: false,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/sign')) {
      srv.signed = true
      const current = (srv.round as { data: { sheet: Record<string, unknown> } }).data.sheet
      return Promise.resolve({ data: { sheet: { ...current, status: 'Submitted' } } })
    }
    if (url.includes('/grading/incompletes/9/resolution')) {
      return Promise.resolve(srv.round)
    }
    if (url.includes('/grading/sheets/')) {
      return Promise.resolve({
        data: { enrollment_id: 5, grade: { mark: 88, letter_grade: 'B+', gpa_points: 3.3 } },
      })
    }
    return Promise.resolve({ data: {} })
  },
}))

function roundPayload(status: string, grade: Record<string, unknown> | null) {
  return {
    data: {
      sheet: { id: 6, course_offering_id: 3, kind: 'IncompleteResolution', status },
      course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ', level: 500 },
      lines: [
        {
          enrollment_id: 5,
          course_status: 'Incomplete',
          credit_hours: 3,
          student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
          grade,
          incomplete: { completion_percentage: 80, reason: 'Hospitalised.' },
        },
      ],
    },
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/grading/incompletes/9']}>
          <Routes>
            <Route
              path="/grading/incompletes/:incompleteId"
              element={<IncompleteResolutionPage />}
            />
            <Route path="/grading/incompletes" element={<div>outstanding incompletes</div>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(() => {
  srv.signed = false
})

describe('IncompleteResolutionPage (ticket 05)', () => {
  it("shows the round's one line and disables signing before the mark is entered", async () => {
    srv.round = roundPayload('Draft', null)
    renderPage()

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText(/1 line is still ungraded/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign and submit/i })).toBeDisabled()
  })

  it('enables signing once the outstanding mark is entered, and signs on click', async () => {
    srv.round = roundPayload('Draft', { mark: 88, letter_grade: 'B+', gpa_points: 3.3 })
    renderPage()

    const signButton = await screen.findByRole('button', { name: /sign and submit/i })
    expect(signButton).toBeEnabled()

    fireEvent.click(signButton)

    await waitFor(() => expect(srv.signed).toBe(true))
  })

  it('offers no Incomplete-declare toggle — only the mark may be entered', async () => {
    srv.round = roundPayload('Draft', null)
    renderPage()

    await screen.findByText('Sara Student')
    expect(
      screen.queryByRole('button', { name: /mark incomplete instead/i }),
    ).not.toBeInTheDocument()
  })
})
