import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { DepartmentHeadSheetPage } from './DepartmentHeadSheetPage'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (as the app always does — the GradeSheetTable test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

// The review GET plus the finalize/return POSTs behind the shared Axios mutator, so the page
// drives the real generated hooks. `srv.review` is the payload; each POST is recorded.
const srv = vi.hoisted(() => ({
  review: undefined as unknown,
  finalized: false,
  returned: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/finalize')) {
      srv.finalized = true
      return Promise.resolve({ data: { sheet: { id: 1, status: 'Finalized' } } })
    }
    if (url.endsWith('/return')) {
      srv.returned = config.data
      return Promise.resolve({ data: { sheet: { id: 1, status: 'Returned' } } })
    }
    if (url.includes('/grading/department-head/sheets/1')) {
      return Promise.resolve(srv.review)
    }
    return Promise.resolve({ data: {} })
  },
}))

function reviewPayload() {
  return {
    data: {
      sheet: { id: 1, course_offering_id: 3, kind: 'Initial', status: 'Submitted' },
      course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ', level: 500 },
      lines: [
        {
          enrollment_id: 5,
          course_status: 'Regular',
          credit_hours: 3,
          student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
          grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3 },
        },
      ],
      distribution: [
        { letter: 'A+', count: 0 },
        { letter: 'B', count: 1 },
      ],
    },
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/grading/review/1']}>
          <Routes>
            <Route path="/grading/review/:sheetId" element={<DepartmentHeadSheetPage />} />
            <Route path="/grading/review" element={<div>queue</div>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(() => {
  srv.review = reviewPayload()
  srv.finalized = false
  srv.returned = undefined
})

describe('DepartmentHeadSheetPage', () => {
  it('shows every mark, read-only, and the grade distribution', async () => {
    renderPage()

    expect(await screen.findByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText('82.5')).toBeInTheDocument()
    // The letter appears twice — once on Sara's line, once in the distribution.
    expect(screen.getAllByText('B')).toHaveLength(2)
    // No mark input exists anywhere — a Department Head can never write a mark.
    expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument()
  })

  it('finalizes the sheet on click', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /finalize/i }))

    await waitFor(() => expect(srv.finalized).toBe(true))
  })

  it('requires a note before returning the sheet', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /return with a note/i }))
    fireEvent.click(screen.getByRole('button', { name: /^return with a note$/i }))

    expect(await screen.findByText(/note is required/i)).toBeInTheDocument()
    expect(srv.returned).toBeUndefined()
  })

  it('returns the sheet with the typed note', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /return with a note/i }))
    fireEvent.change(screen.getByLabelText(/note to the instructor/i), {
      target: { value: 'Please recheck line 5.' },
    })
    fireEvent.click(screen.getByRole('button', { name: /^return with a note$/i }))

    await waitFor(() => expect(srv.returned).toEqual({ note: 'Please recheck line 5.' }))
  })
})
