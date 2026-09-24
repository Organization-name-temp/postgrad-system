import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { WaiversPage } from './WaiversPage'

const api = vi.hoisted(() => ({
  calls: [] as AxiosRequestConfig[],
  requests: [] as unknown[],
  downloadError: false,
}))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    const url = config.url ?? ''
    if (url.includes('/evidence-documents/')) {
      return api.downloadError
        ? Promise.reject(new Error('download failed'))
        : Promise.resolve(new Blob(['transcript']))
    }
    if (url.endsWith('/targets'))
      return Promise.resolve({
        data: [
          {
            target_course_id: 5,
            target_course_code: 'CSE-511',
            category: 'ProgramCore',
            credit_hours: 3,
            already_recognized: false,
          },
        ],
      })
    if (url.endsWith('/prior-results'))
      return Promise.resolve({
        data: [
          {
            enrollment_id: 12,
            course: {
              id: 8,
              code: 'CSE-411',
              name_en: 'Algorithms',
              name_ar: 'الخوارزميات',
              level: 400,
            },
            minimum_result: 'C+',
          },
        ],
      })
    if (url.endsWith('/evidence-documents'))
      return Promise.resolve({
        data: [
          {
            id: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            type: 'transcript',
            original_filename: 'legacy-transcript.pdf',
          },
        ],
      })
    if (url.endsWith('/courses'))
      return Promise.resolve({
        data: [
          {
            id: 8,
            code: 'CSE-411',
            name_en: 'Algorithms',
            name_ar: 'الخوارزميات',
            level: 400,
          },
        ],
      })
    if (url.endsWith('/waiver-requests') && config.method === 'GET')
      return Promise.resolve({ data: api.requests, meta: { total: api.requests.length } })
    return Promise.resolve({ data: { id: 1, status: 'Draft', revision: 1 } })
  },
}))

function renderPage(locale: 'en' | 'ar' = 'en') {
  return render(
    <ThemeProvider theme={createAppTheme(locale)}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <WaiversPage />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(async () => {
  api.calls = []
  api.requests = []
  api.downloadError = false
  await i18n.changeLanguage('en')
})

it('creates a Waiver Draft from an eligible PRS result and bound Curriculum target', async () => {
  renderPage()

  expect(await screen.findByText(/no waiver requests yet/i)).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText(/target curriculum course/i), {
    target: { value: '5' },
  })
  fireEvent.blur(screen.getByLabelText(/target curriculum course/i))
  fireEvent.change(screen.getByLabelText(/prior e-just result/i), {
    target: { value: '12' },
  })
  fireEvent.blur(screen.getByLabelText(/prior e-just result/i))
  const save = screen.getByRole('button', { name: /save draft/i })
  await vi.waitFor(() => expect(save).toBeEnabled())
  fireEvent.click(save)

  await vi.waitFor(() =>
    expect(
      api.calls.find((call) => call.method === 'POST' && call.url?.endsWith('/waiver-requests'))
        ?.data,
    ).toEqual({
      target_course_id: 5,
      prior_result: { source: 'prs', institution: 'E-JUST', enrollment_id: 12 },
      student_note: undefined,
    }),
  )
})

it('keeps course codes, grades, and named legacy evidence LTR in the Arabic RTL form', async () => {
  await i18n.changeLanguage('ar')
  renderPage('ar')
  await screen.findByText(/لا توجد طلبات إعفاء من مقرر/i)

  fireEvent.change(screen.getByLabelText(/مصدر النتيجة السابقة/i), {
    target: { value: 'legacy_transcript' },
  })
  expect(screen.getByLabelText(/رمز المقرر السابق/i)).toHaveAttribute('dir', 'ltr')
  expect(screen.getByLabelText(/تقدير المقرر السابق/i)).toHaveAttribute('dir', 'ltr')
  expect(screen.getByRole('checkbox', { name: 'legacy-transcript.pdf' })).toBeInTheDocument()
})

it('shows locked evidence, immutable verification history, and the Council outcome', async () => {
  api.requests = [
    {
      id: 7,
      target_course_code: 'CSE-511',
      prior_course_code: 'CSE-411',
      prior_course_level: 400,
      prior_result_source: 'legacy_transcript',
      prior_letter_grade: 'B',
      evidence: {
        source: 'legacy_transcript',
        official_transcript_document_ids: ['01ARZ3NDEKTSV4RRFFQ69G5FAW'],
      },
      status: 'Approved',
      revision: 1,
      editable: false,
      head_verification: {
        coverage_percentage: 85.5,
        equivalency_note: 'Learning outcomes align.',
        verified_by: 22,
        verified_at: '2026-09-05T09:00:00+00:00',
      },
      transitions: [
        {
          action: 'approved_by_council',
          revision: 1,
          current_revision: true,
          created_at: '2026-09-06T09:00:00+00:00',
        },
      ],
      decision: { approved_at: '2026-09-06T09:00:00+00:00' },
    },
  ]
  renderPage()

  expect(await screen.findByText('85.5%')).toBeInTheDocument()
  expect(screen.getByText('Learning outcomes align.')).toBeInTheDocument()
  expect(screen.getByText(/council approved/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'legacy-transcript.pdf' })).toBeInTheDocument()
})

it('reports a transcript download failure without leaving an unhandled rejection', async () => {
  api.downloadError = true
  api.requests = [
    {
      id: 7,
      target_course_code: 'CSE-511',
      prior_course_code: 'CSE-411',
      prior_course_level: 400,
      prior_result_source: 'legacy_transcript',
      prior_letter_grade: 'B',
      evidence: {
        source: 'legacy_transcript',
        official_transcript_document_ids: ['01ARZ3NDEKTSV4RRFFQ69G5FAW'],
      },
      status: 'Approved',
      revision: 1,
      editable: false,
      transitions: [],
    },
  ]
  renderPage()

  fireEvent.click(await screen.findByRole('button', { name: 'legacy-transcript.pdf' }))

  expect(await screen.findByRole('alert')).toHaveTextContent(/could not be downloaded/i)
})
