import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ApplicationPipelineReportPage } from './ApplicationPipelineReportPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.includes('application-pipeline')) {
      return Promise.resolve({
        data: {
          total: 3,
          statuses: { accepted: 2, rejected: 1 },
          conversions: [
            {
              from_status: 'interview_completed',
              to_status: 'accepted',
              eligible: 3,
              converted: 2,
              rate: 66.67,
            },
          ],
        },
      })
    }
    return Promise.resolve({ data: [], meta: { total: 0 } })
  },
}))

beforeEach(() => {
  api.calls = []
})
it.each(['en', 'ar'] as const)(
  'restores %s criteria and gives the table the exact stage drill URL',
  async (locale) => {
    await i18n.changeLanguage(locale)
    const user = userEvent.setup()
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter
            initialEntries={[
              '/analytics/application-pipeline?admission_cycle_id=7&program_id=4&degree_level=MSc',
            ]}
          >
            <ApplicationPipelineReportPage />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const title = locale === 'en' ? 'Application Pipeline' : 'مسار طلبات القبول'
    expect(await screen.findByRole('img', { name: title })).toBeInTheDocument()
    expect(
      api.calls.find((call) => call.url?.includes('application-pipeline'))?.params,
    ).toMatchObject({ admission_cycle_id: 7, program_id: 4, degree_level: 'MSc' })
    await user.click(
      screen.getByRole('button', {
        name: locale === 'en' ? 'View as table' : i18n.t('collection.viewTable'),
      }),
    )
    const table = screen.getByRole('table', { name: title })
    const accepted = locale === 'en' ? 'Accepted' : i18n.t('status.accepted', { ns: 'admissions' })
    expect(within(table).getByRole('link', { name: accepted })).toHaveAttribute(
      'href',
      '/screening?admission_cycle_id=7&program_id=4&degree_level=MSc&status=accepted',
    )
    expect(within(table).getByText('2')).toBeInTheDocument()
    expect(screen.getByText('66.67%')).toBeInTheDocument()
  },
)
