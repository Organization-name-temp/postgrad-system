import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config'
import { CorrectionSheetPage } from './CorrectionSheetPage'

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

const srv = vi.hoisted(() => ({
  sheet: undefined as unknown,
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
      return Promise.resolve({ data: { sheet: { id: 7, status: 'Submitted' } } })
    }
    if (url.includes('/grading/corrections/7')) {
      return Promise.resolve(srv.sheet)
    }
    return Promise.resolve({ data: {} })
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/grading/corrections/7']}>
          <Routes>
            <Route path="/grading/corrections/:sheetId" element={<CorrectionSheetPage />} />
            <Route path="/grading/offerings/:offeringId" element={<div>offering</div>} />
            <Route path="/grading" element={<div>grading</div>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(() => {
  srv.signed = false
  srv.sheet = {
    data: {
      sheet: { id: 7, course_offering_id: 3, kind: 'Correction', status: 'Draft' },
      course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ', level: 500 },
      lines: [
        {
          enrollment_id: 5,
          course_status: 'Regular',
          credit_hours: 3,
          student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
          grade: { mark: 91, letter_grade: 'A', gpa_points: 3.7 },
          grade_history: [{ mark: 82.5, letter_grade: 'B', gpa_points: 3, superseded: true }],
        },
      ],
    },
  }
})

describe('CorrectionSheetPage', () => {
  it('shows the earlier finalized grade as context for the correction', async () => {
    renderPage()

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText(/earlier finalized grades: b \(82\.50%, superseded\)/i)).toBeInTheDocument()
  })

  it('signs the correction round', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /sign correction for review/i }))

    await waitFor(() => expect(srv.signed).toBe(true))
  })
})
