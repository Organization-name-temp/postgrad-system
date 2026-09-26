import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { WaiverReviewQueuePage } from './WaiverReviewQueuePage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], approved: false }))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.method === 'GET')
      return Promise.resolve({
        data: [
          {
            id: 9,
            target_course_code: 'CSE-511',
            prior_course_code: 'CSE-411',
            prior_course_level: 400,
            status: api.approved
              ? 'Approved'
              : config.url?.includes('council-queue')
                ? 'PendingCouncil'
                : 'PendingDepartmentHead',
            revision: 2,
            student: { name: 'Sara Student', university_id: '2026-CSE-0001' },
            evidence: {
              source: 'legacy_transcript',
              institution: 'E-JUST',
              letter_grade: 'A',
              official_transcript_document_ids: ['01ARZ3NDEKTSV4RRFFQ69G5FAW'],
            },
            evidence_documents: [
              {
                id: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                original_filename: 'legacy-transcript.pdf',
                download_url:
                  '/api/v1/credit-decisions/waiver-requests/9/evidence-documents/01ARZ3NDEKTSV4RRFFQ69G5FAW',
              },
            ],
            transitions: [],
            decision: api.approved
              ? { id: 83, credit_hours: 3, effective: true, revocation: null }
              : null,
          },
        ],
        meta: { total: 1 },
      })
    return Promise.resolve({ data: { id: 9, status: 'PendingCouncil', revision: 2 } })
  },
}))

function renderPage(stage: 'advisor' | 'departmentHead' | 'council') {
  return render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <WaiverReviewQueuePage stage={stage} />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(async () => {
  api.calls = []
  api.approved = false
  await i18n.changeLanguage('en')
})

it('requires and submits Department Head equivalency verification at the 80% boundary', async () => {
  renderPage('departmentHead')
  await screen.findByText('Sara Student')
  expect(screen.getByRole('link', { name: 'legacy-transcript.pdf' })).toBeInTheDocument()

  const verify = screen.getByRole('button', { name: /verify for council/i })
  fireEvent.change(screen.getByLabelText(/verified content coverage/i), {
    target: { value: '79.99' },
  })
  fireEvent.blur(screen.getByLabelText(/verified content coverage/i))
  expect(await screen.findByRole('alert')).toHaveTextContent(/at least 80%/i)
  expect(verify).toBeDisabled()

  fireEvent.change(screen.getByLabelText(/verified content coverage/i), {
    target: { value: '80' },
  })
  fireEvent.change(screen.getByLabelText(/equivalency note/i), {
    target: { value: 'Outcomes and assessment align.' },
  })
  fireEvent.blur(screen.getByLabelText(/equivalency note/i))
  await vi.waitFor(() => expect(verify).toBeEnabled())
  fireEvent.click(verify)

  await vi.waitFor(() =>
    expect(api.calls.find((call) => call.method === 'POST')?.data).toEqual(
      expect.objectContaining({
        action: 'verify',
        coverage_percentage: 80,
        equivalency_note: 'Outcomes and assessment align.',
        expected_revision: 2,
      }),
    ),
  )
})

it.each([
  ['advisor', 'Recommend to Department Head'],
  ['council', 'Approve Waiver'],
] as const)('offers the correct %s stage action', async (stage, label) => {
  renderPage(stage)
  await screen.findByText('Sara Student')

  expect(screen.getByRole('button', { name: label })).toBeInTheDocument()
})

it('lets Council revoke an effective Waiver decision with a reason', async () => {
  api.approved = true
  renderPage('council')
  await screen.findByText('Sara Student')

  fireEvent.change(screen.getByLabelText(/review reason/i), {
    target: { value: 'Council correction.' },
  })
  fireEvent.click(screen.getByRole('button', { name: /revoke decision/i }))
  await screen.findByText(/original decision and its review history/i)
  fireEvent.click(await screen.findByRole('button', { name: /revoke decision/i }))

  await vi.waitFor(() => {
    const call = api.calls.find((candidate) => candidate.url?.endsWith('/waivers/83/revoke'))
    expect(call?.data).toEqual({ reason: 'Council correction.' })
  })
})
