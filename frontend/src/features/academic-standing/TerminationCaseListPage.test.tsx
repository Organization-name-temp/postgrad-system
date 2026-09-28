import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import type { AxiosRequestConfig } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { TerminationCaseListPage } from './TerminationCaseListPage'

const api = vi.hoisted(() => ({ oversight: false, calls: [] as AxiosRequestConfig[] }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/me')
      return Promise.resolve({
        data: {
          permissions: ['standing.monitor', ...(api.oversight ? [] : ['termination.recommend'])],
          roles: [{ code: api.oversight ? 'R05' : 'R03' }],
        },
      })
    if (config.url?.endsWith('/reports/termination/cases'))
      return Promise.resolve({
        data: [
          {
            case_id: 101,
            student_id: 42,
            university_id: '001-STUDENT',
            name_en: 'Sara Student',
            name_ar: 'سارة الطالبة',
            program: null,
            lifecycle: 'Active',
            state: 'AwaitingAdvisor',
            created_at: '2026-01-03T23:59:00+00:00',
            advisor_reviewed_at: null,
            department_head_reviewed_at: null,
            council_decided_at: null,
            closed_at: null,
            review_url: '/termination/advisor',
            unavailable: ['program'],
          },
          {
            case_id: 102,
            student_id: 43,
            university_id: '002-STUDENT',
            name_en: 'Other Student',
            name_ar: 'طالب آخر',
            program: null,
            lifecycle: 'Active',
            state: 'AwaitingCouncil',
            created_at: '2026-01-03T23:59:00+00:00',
            advisor_reviewed_at: null,
            department_head_reviewed_at: null,
            council_decided_at: null,
            closed_at: null,
            review_url: '/termination/advisor',
            unavailable: ['program'],
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 2 },
      })
    return Promise.resolve({ data: [] })
  },
}))
beforeEach(() => {
  api.oversight = false
  api.calls = []
})

it.each(['en', 'ar'] as const)(
  'shows %s minimal timestamps and only stage/access-aware operational links',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter
            initialEntries={[
              '/analytics/termination/cases?history=include&created_from=2026-01-01&sort=-created_at',
            ]}
          >
            <TerminationCaseListPage />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const first = await screen.findByRole('row', { name: /001-STUDENT/ })
    expect(within(first).getByText('2026-01-03 23:59')).toHaveAttribute('dir', 'ltr')
    expect(
      await within(first).findByRole('link', {
        name: locale === 'en' ? 'Open authorized review workflow' : 'فتح مسار المراجعة المصرح به',
      }),
    ).toHaveAttribute('href', '/termination/advisor')
    const wrongStage = screen.getByRole('row', { name: /002-STUDENT/ })
    expect(within(wrongStage).queryByRole('link')).not.toBeInTheDocument()
    expect(
      api.calls.find((call) => call.url?.endsWith('/reports/termination/cases'))?.params,
    ).toMatchObject({
      history: 'include',
      created_from: '2026-01-01',
      sort: '-created_at',
      per_page: 25,
    })
  },
)

it('hides operational links from an oversight-only persona even when a stale payload contains one', async () => {
  api.oversight = true
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter>
          <TerminationCaseListPage />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
  const row = await screen.findByRole('row', { name: /001-STUDENT/ })
  expect(within(row).queryByRole('link')).not.toBeInTheDocument()
})
