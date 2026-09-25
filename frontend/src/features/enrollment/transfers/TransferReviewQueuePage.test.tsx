import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { TransferReviewQueuePage, type TransferReviewStage } from './TransferReviewQueuePage'

const api = vi.hoisted(() => ({
  calls: [] as AxiosRequestConfig[],
  failPost: false,
  empty: false,
  approved: false,
}))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.method === 'POST' && api.failPost) return Promise.reject(new Error('response lost'))
    if (config.method === 'GET')
      return Promise.resolve({
        data: api.empty
          ? []
          : [
              {
                id: 9,
                target_course_code: 'CSE501',
                target_category: 'ProgramCore',
                target_credit_hours: 3,
                external_institution: 'Kyoto University',
                external_course_code: 'CS501',
                external_course_name: 'Algorithms',
                source_credit_hours: '4.00',
                external_grade: 'Excellent',
                document_ids: ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
                evidence_documents: [
                  {
                    id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    original_filename: 'transcript.pdf',
                    download_url:
                      '/api/v1/credit-decisions/transfer-requests/9/evidence-documents/01ARZ3NDEKTSV4RRFFQ69G5FAV',
                  },
                ],
                status: api.approved
                  ? 'Approved'
                  : config.url?.includes('council-queue')
                    ? 'PendingCouncil'
                    : 'PendingDepartmentHead',
                revision: 1,
                student: { name: 'Sara Student', university_id: '2026-CSE-0001' },
                transitions: [
                  {
                    action: 'recommended_by_advisor',
                    reason: 'Equivalent outline',
                    revision: 1,
                    current_revision: true,
                  },
                ],
                decision: api.approved
                  ? {
                      id: 82,
                      normalized_grade: 'A',
                      target_credit_hours: 3,
                      effective: true,
                      revocation: null,
                    }
                  : null,
              },
            ],
        meta: { total: api.empty ? 0 : 1, last_page: 1 },
      })
    return Promise.resolve({ data: { id: 9 } })
  },
}))

function renderQueue(stage: TransferReviewStage, locale: 'en' | 'ar' = 'en') {
  return render(
    <ThemeProvider theme={createAppTheme(locale)}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <TransferReviewQueuePage stage={stage} />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(async () => {
  api.calls = []
  api.failPost = false
  api.empty = false
  api.approved = false
  await i18n.changeLanguage('en')
})

it('requires and submits one of the four normalized grades at the scoped Head seam', async () => {
  renderQueue('departmentHead')
  expect(await screen.findByText('Sara Student')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'transcript.pdf' })).toHaveAttribute(
    'href',
    '/api/v1/credit-decisions/transfer-requests/9/evidence-documents/01ARZ3NDEKTSV4RRFFQ69G5FAV',
  )
  fireEvent.click(screen.getByRole('button', { name: /verify equivalency/i }))
  expect(await screen.findByRole('alert')).toHaveTextContent(/required/i)
  fireEvent.mouseDown(screen.getByLabelText(/normalized e-just grade/i))
  fireEvent.click(await screen.findByText('B+'))
  fireEvent.click(screen.getByRole('button', { name: /verify equivalency/i }))

  await vi.waitFor(() => expect(api.calls.some((call) => call.method === 'POST')).toBe(true))
  expect(api.calls.find((call) => call.method === 'POST')?.data).toEqual(
    expect.objectContaining({
      action: 'verify',
      normalized_grade: 'B+',
      expected_revision: 1,
    }),
  )
}, 10_000)

it('keeps the idempotency key stable when a Council retry follows a lost response', async () => {
  api.failPost = true
  renderQueue('council')
  expect(await screen.findByText('Sara Student')).toBeInTheDocument()
  const approve = screen.getByRole('button', { name: /^approve$/i })
  fireEvent.click(approve)
  await screen.findByText(/no longer authorized/i)
  api.failPost = false
  fireEvent.click(approve)
  await waitFor(() => expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(2))
  const posts = api.calls.filter((call) => call.method === 'POST')
  expect(posts[0]?.data.action_id).toBe(posts[1]?.data.action_id)
})

it('requires a reason and confirmation before terminal rejection', async () => {
  renderQueue('advisor')
  expect(await screen.findByText('Sara Student')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /^reject$/i }))
  expect(await screen.findByRole('alert')).toHaveTextContent(/required/i)
  fireEvent.change(screen.getByLabelText(/review reason/i), {
    target: { value: 'Evidence is not official.' },
  })
  fireEvent.click(screen.getByRole('button', { name: /^reject$/i }))
  fireEvent.click(await screen.findByRole('button', { name: /reject request/i }))

  await waitFor(() => expect(api.calls.some((call) => call.method === 'POST')).toBe(true))
  expect(api.calls.find((call) => call.method === 'POST')?.data).toEqual(
    expect.objectContaining({ action: 'reject', reason: 'Evidence is not official.' }),
  )
})

it('renders the Advisor queue and immutable evidence history in Arabic RTL', async () => {
  await i18n.changeLanguage('ar')
  renderQueue('advisor', 'ar')
  expect(await screen.findByRole('heading', { name: 'توصيات التحويل' })).toBeInTheDocument()
  expect(await screen.findByText('Sara Student')).toBeInTheDocument()
  expect(screen.getByText('Equivalent outline')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'التوصية' })).toBeInTheDocument()
})

it('shows an explicit empty reviewer state', async () => {
  api.empty = true
  renderQueue('council')
  expect(await screen.findByText(/no Transfer requests are waiting/i)).toBeInTheDocument()
})

it('lets Council revoke an effective Transfer decision with a reason', async () => {
  api.approved = true
  renderQueue('council')
  await screen.findByText('Sara Student')

  fireEvent.change(screen.getByLabelText(/review reason/i), {
    target: { value: 'Council correction.' },
  })
  fireEvent.click(screen.getByRole('button', { name: /revoke decision/i }))
  await screen.findByText(/original decision and its review history/i)
  fireEvent.click(await screen.findByRole('button', { name: /revoke decision/i }))

  await waitFor(() => {
    const call = api.calls.find((candidate) => candidate.url?.endsWith('/transfers/82/revoke'))
    expect(call?.data).toEqual({ reason: 'Council correction.' })
  })
})
