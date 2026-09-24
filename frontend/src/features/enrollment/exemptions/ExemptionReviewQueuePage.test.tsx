import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import { getAdvisorManualExemptionQueueQueryKey } from '../../../shared/api/generated/endpoints'
import i18n from '../../../shared/i18n/config'
import { ExemptionReviewQueuePage } from './ExemptionReviewQueuePage'

const theme = createAppTheme('en')

const api = vi.hoisted(() => ({
  calls: [] as AxiosRequestConfig[],
  failNextPost: false,
  failGets: false,
  removeLastPageOnPost: false,
  lastPageRemoved: false,
  approved: false,
}))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.method === 'GET' && api.failGets) return Promise.reject(new Error('unavailable'))
    if (config.method === 'POST' && api.failNextPost) {
      api.failNextPost = false
      return Promise.reject(new Error('response lost'))
    }
    if (config.method === 'POST' && api.removeLastPageOnPost) {
      api.lastPageRemoved = true
      return Promise.resolve({ data: { id: 10, status: 'PendingDepartmentHead' } })
    }
    if (config.method === 'GET' && api.removeLastPageOnPost) {
      const page = Number(config.params?.page ?? 1)
      if (page === 2) {
        return Promise.resolve({
          data: api.lastPageRemoved
            ? []
            : [
                {
                  id: 10,
                  target_course_code: 'PRE402',
                  preparatory_requirement_role: 'EnglishLanguage',
                  status: 'PendingAdvisor',
                  revision: 1,
                  student: { name: 'Final Page Student', university_id: '2026-CSE-0002' },
                  evidence: { english_test: 'IELTS', english_score: 7 },
                  transitions: [],
                },
              ],
          meta: {
            total: api.lastPageRemoved ? 1 : 2,
            current_page: 2,
            last_page: api.lastPageRemoved ? 1 : 2,
          },
        })
      }

      return Promise.resolve({
        data: [
          {
            id: 9,
            target_course_code: 'PRE401',
            preparatory_requirement_role: 'EnglishLanguage',
            status: 'PendingAdvisor',
            revision: 1,
            student: { name: 'Earlier Page Student', university_id: '2026-CSE-0001' },
            evidence: { english_test: 'IELTS', english_score: 7 },
            transitions: [],
          },
        ],
        meta: {
          total: api.lastPageRemoved ? 1 : 2,
          current_page: 1,
          last_page: api.lastPageRemoved ? 1 : 2,
        },
      })
    }
    if (config.method === 'GET')
      return Promise.resolve({
        data: [
          {
            id: 9,
            target_course_code: 'PRE401',
            preparatory_requirement_role: 'EnglishLanguage',
            status: api.approved ? 'Approved' : 'PendingAdvisor',
            revision: 1,
            student: { name: 'Sara Student', university_id: '2026-CSE-0001' },
            evidence: { english_test: 'IELTS', english_score: 7 },
            transitions: [
              {
                action: 'submitted',
                reason: 'Readable report supplied',
                revision: 1,
                current_revision: true,
              },
            ],
            decision: api.approved
              ? { id: 81, credit_hours: 3, effective: true, revocation: null }
              : null,
          },
        ],
        meta: { total: 1 },
      })
    return Promise.resolve({ data: { id: 9, status: 'PendingDepartmentHead' } })
  },
}))

beforeEach(async () => {
  api.calls = []
  api.failNextPost = false
  api.failGets = false
  api.removeLastPageOnPost = false
  api.lastPageRemoved = false
  api.approved = false
  URL.createObjectURL = vi.fn(() => 'blob:review')
  URL.revokeObjectURL = vi.fn()
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
  await i18n.changeLanguage('en')
})

it('shows an initial-load error without also claiming the review queue is empty', async () => {
  api.failGets = true
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByText(/could not load exemption requests/i)).toBeInTheDocument()
  expect(screen.queryByText(/no requests are waiting for review/i)).not.toBeInTheDocument()
})

it('hides cached review cards when their refetch fails', async () => {
  api.failGets = true
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(getAdvisorManualExemptionQueueQueryKey({ page: 1 }), {
    data: [
      {
        id: 9,
        target_course_code: 'PRE401',
        status: 'PendingAdvisor',
        revision: 1,
        student: { name: 'Cached Student', university_id: '2026-CSE-0001' },
        transitions: [],
      },
    ],
    meta: { total: 1 },
  })
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider client={client}>
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByText(/could not load exemption requests/i)).toBeInTheDocument()
  expect(screen.queryByText('Cached Student')).not.toBeInTheDocument()
})

it('lets the Advisor recommend a live-scoped request', async () => {
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  expect(await screen.findByText('Sara Student')).toBeInTheDocument()
  expect(screen.getByText('Submitted to Advisor')).toBeInTheDocument()
  expect(screen.getByText('Readable report supplied')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /recommend/i }))
  await vi.waitFor(() => expect(api.calls.some((call) => call.method === 'POST')).toBe(true))
})

it('uses the named large-radius token for reviewer panels', async () => {
  const { container } = render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByText('Sara Student')

  const panel = container.querySelector<HTMLElement>('.MuiPaper-outlined')
  expect(panel).not.toBeNull()
  expect(getComputedStyle(panel!).borderRadius).toBe('12px')
})

it.each([
  ['en', /review reason/i, 'Use 2,000 characters or fewer.'],
  ['ar', /سبب المراجعة/i, 'استخدم 2000 حرف أو أقل.'],
])(
  'localizes an overlength review reason in %s without exposing a translation key',
  async (locale, reasonLabel, expected) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={theme}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <ExemptionReviewQueuePage stage="advisor" />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    await screen.findByText('Sara Student')

    const reason = screen.getByLabelText(reasonLabel)
    fireEvent.change(reason, { target: { value: 'x'.repeat(2001) } })
    fireEvent.blur(reason)

    expect(await screen.findByRole('alert')).toHaveTextContent(expected)
    expect(screen.queryByText('manualExemptions.tooLong')).not.toBeInTheDocument()
  },
)

it('does not send an action whose review reason fails structured validation', async () => {
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByText('Sara Student')

  fireEvent.change(screen.getByLabelText(/review reason/i), {
    target: { value: 'x'.repeat(2001) },
  })
  fireEvent.click(screen.getByRole('button', { name: /recommend/i }))

  expect(await screen.findByRole('alert')).toHaveTextContent(/2,000 characters or fewer/i)
  expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
})

it('returns to the last available page after acting on the final row of a later page', async () => {
  api.removeLastPageOnPost = true
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  fireEvent.click(await screen.findByRole('button', { name: /go to page 2/i }))
  expect(await screen.findByText('Final Page Student')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /recommend/i }))

  expect(await screen.findByText('Earlier Page Student')).toBeInTheDocument()
  expect(screen.queryByText(/no requests are waiting for review/i)).not.toBeInTheDocument()
})

it('reuses the action id when a reviewer retries a lost response', async () => {
  api.failNextPost = true
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  const recommend = await screen.findByRole('button', { name: /recommend/i })
  fireEvent.click(recommend)
  expect(await screen.findByText(/request changed/i)).toBeInTheDocument()
  fireEvent.click(recommend)

  await vi.waitFor(() => {
    const calls = api.calls.filter((call) => call.method === 'POST')
    expect(calls).toHaveLength(2)
    expect(calls[0]?.data?.action_id).toBe(calls[1]?.data?.action_id)
  })
})

it('exports the active search through the generated sibling client', async () => {
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="advisor" />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByText('Sara Student')
  fireEvent.change(screen.getByLabelText(/search requests/i), { target: { value: 'PRE' } })
  fireEvent.click(screen.getByRole('button', { name: /export xlsx/i }))

  await vi.waitFor(() =>
    expect(
      api.calls.some(
        (call) =>
          call.method === 'GET' &&
          call.url?.endsWith('/advisor-queue/export') &&
          call.params?.q === 'PRE' &&
          call.params?.page === 1,
      ),
    ).toBe(true),
  )
})

it('lets the scoped Department Head revoke an effective decision with a reason', async () => {
  api.approved = true
  render(
    <ThemeProvider theme={theme}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ExemptionReviewQueuePage stage="departmentHead" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  await screen.findByText('Sara Student')
  fireEvent.change(screen.getByLabelText(/review reason/i), {
    target: { value: 'Evidence was withdrawn.' },
  })
  fireEvent.click(screen.getByRole('button', { name: /revoke decision/i }))
  await screen.findByText(/original decision and its review history/i)
  fireEvent.click(await screen.findByRole('button', { name: /revoke decision/i }))

  await vi.waitFor(() => {
    const call = api.calls.find((candidate) => candidate.url?.endsWith('/exemptions/81/revoke'))
    expect(call?.data).toEqual({ reason: 'Evidence was withdrawn.' })
  })
})
