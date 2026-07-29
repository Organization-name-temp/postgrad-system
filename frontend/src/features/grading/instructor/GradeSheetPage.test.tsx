import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { GradeSheetPage } from './GradeSheetPage'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (as the app always does — the GradeSheetTable test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

// The sheet GET and the sign POST behind the shared Axios mutator, so the page drives the real
// generated hooks. `srv.sheet` is the payload; the sign POST is recorded so a test can assert
// whether it was ever sent.
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
      const current = (srv.sheet as { data: { sheet: Record<string, unknown> } }).data.sheet
      return Promise.resolve({ data: { sheet: { ...current, status: 'Submitted' } } })
    }
    if (url.includes('/grading/offerings/3/sheet')) {
      return Promise.resolve(srv.sheet)
    }
    return Promise.resolve({ data: {} })
  },
}))

function sheetPayload(status: string, lines: Record<string, unknown>[]) {
  return {
    data: {
      sheet: { id: 1, course_offering_id: 3, kind: 'Initial', status },
      course: { id: 1, code: 'DEP501', name_en: 'Advanced Algorithms', name_ar: 'خ', level: 500 },
      lines,
    },
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={['/grading/offerings/3']}>
          <Routes>
            <Route path="/grading/offerings/:offeringId" element={<GradeSheetPage />} />
            <Route path="/grading" element={<div>grade sheets</div>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(() => {
  srv.signed = false
})

describe('GradeSheetPage — signing (ticket 03)', () => {
  it('disables signing while a line is still blank, and names how many remain', async () => {
    srv.sheet = sheetPayload('Draft', [
      {
        enrollment_id: 5,
        course_status: 'Regular',
        credit_hours: 3,
        student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
        grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3 },
      },
      {
        enrollment_id: 6,
        course_status: 'Regular',
        credit_hours: 3,
        student: { name: 'Omar Student', university_id: '2026-MSC-CE-0002' },
        grade: null,
      },
    ])
    renderPage()

    expect(await screen.findByText(/1 line is still ungraded/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign and submit/i })).toBeDisabled()
  })

  it('enables signing once every line is graded, and signs on click', async () => {
    srv.sheet = sheetPayload('Draft', [
      {
        enrollment_id: 5,
        course_status: 'Regular',
        credit_hours: 3,
        student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
        grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3 },
      },
    ])
    renderPage()

    const signButton = await screen.findByRole('button', { name: /sign and submit/i })
    expect(signButton).toBeEnabled()

    fireEvent.click(signButton)

    await waitFor(() => expect(srv.signed).toBe(true))
  })

  it("shows the department head's return note, and the sheet is signable again", async () => {
    srv.sheet = sheetPayload('Returned', [
      {
        enrollment_id: 5,
        course_status: 'Regular',
        credit_hours: 3,
        student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
        grade: { mark: 55, letter_grade: 'F', gpa_points: 0 },
      },
    ])
    ;(srv.sheet as { data: { sheet: Record<string, unknown> } }).data.sheet.review_note =
      'Please recheck this mark.'
    renderPage()

    expect(await screen.findByText(/please recheck this mark/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign and submit/i })).toBeEnabled()
  })

  it('does not offer to sign a submitted sheet', async () => {
    srv.sheet = sheetPayload('Submitted', [
      {
        enrollment_id: 5,
        course_status: 'Regular',
        credit_hours: 3,
        student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
        grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3 },
      },
    ])
    renderPage()

    await screen.findByText('Sara Student')
    expect(screen.queryByRole('button', { name: /sign and submit/i })).not.toBeInTheDocument()
  })
})
