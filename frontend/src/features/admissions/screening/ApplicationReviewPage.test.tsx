import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ApplicationReviewPage } from './ApplicationReviewPage'

// A tiny in-memory server behind the shared mutator, so the screen drives the REAL
// generated hooks (the detail + timeline reads) — proving the page renders the full
// record the API returns, not a stub.
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/timeline')) {
      return Promise.resolve({ data: [] })
    }
    // The application detail (GET /applications/1) — the full record.
    return Promise.resolve({
      data: {
        id: 1,
        status: 'under_sa_review',
        name_en: 'Sara Applicant',
        national_id: '29001010101010',
        id_type: 'national_id',
        phone: '+201000000000',
        date_of_birth: '1998-05-20',
        nationality: 'EG',
        gender: 'Female',
        address_country: 'Egypt',
        address_city: 'Alexandria',
        address_street: '15 Corniche Road',
        previous_university: 'Cairo University',
        graduation_cgpa: '3.45',
        gpa_scale: '4.0',
        applying_for_degree: 'MSc',
        english_test: 'IELTS',
        english_score: '7.50',
        primary_program: { id: 10, code: 'MSC-CE', name_en: 'MSc in Computer Engineering' },
        documents: [],
      },
    })
  },
}))

const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <MemoryRouter initialEntries={['/screening/1']}>
          <Routes>
            <Route path="/screening/:applicationId" element={<ApplicationReviewPage />} />
            <Route path="/screening" element={<div>screening queue</div>} />
          </Routes>
        </MemoryRouter>
      </ThemeProvider>
    </QueryClientProvider>,
  )
}

describe('ApplicationReviewPage', () => {
  it('renders the full applicant record, not the old six-field summary', async () => {
    renderPage()

    // The applicant loads, headed by their name and a screening decision panel.
    await screen.findByRole('heading', { name: 'Sara Applicant' })
    expect(screen.getByRole('button', { name: /approve for dean review/i })).toBeInTheDocument()

    // Fields that the old six-field summary omitted are now shown, grouped in sections.
    expect(screen.getByText('Contact & address')).toBeInTheDocument()
    expect(screen.getByText('15 Corniche Road')).toBeInTheDocument()
    expect(screen.getByText('3.45')).toBeInTheDocument() // graduation_cgpa
  })
})
