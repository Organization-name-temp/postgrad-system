import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ApplicationPipelineReportPage } from './ApplicationPipelineReportPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], pending: false }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/admission-cycles') {
      return Promise.resolve({
        data:
          config.params?.id === '7'
            ? [{ id: 7, name_en: 'Autumn cohort', name_ar: 'دفعة الخريف' }]
            : [],
        meta: { total: 101 },
      })
    }
    if (config.url === '/api/v1/programs') {
      if (config.params?.q === 'حاسوب' || config.params?.id === '309')
        return Promise.resolve({
          data: [
            {
              id: 309,
              code: 'MSC-SE',
              name_en: 'Software Engineering',
              name_ar: 'هندسة البرمجيات',
            },
          ],
          meta: { total: 1 },
        })
      return Promise.resolve({
        data:
          config.params?.id === '4'
            ? [{ id: 4, code: 'MSC-CE', name_en: 'Computer Engineering', name_ar: 'هندسة الحاسوب' }]
            : [],
        meta: { total: 101 },
      })
    }
    if (config.url?.includes('application-pipeline')) {
      if (api.pending) return new Promise(() => {})
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
  api.pending = false
})
function History() {
  const location = useLocation()
  const navigate = useNavigate()
  return (
    <>
      <output aria-label="URL">{location.search}</output>
      <button onClick={() => navigate(-1)}>Back</button>
    </>
  )
}
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
            <History />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    if (locale === 'ar') expect(await screen.findByText('عدد الطلبات: 3')).toBeInTheDocument()
    const title = locale === 'en' ? 'Application Pipeline' : 'مسار طلبات القبول'
    expect(await screen.findByRole('img', { name: title })).toBeInTheDocument()
    expect(
      await screen.findByDisplayValue(locale === 'en' ? 'Autumn cohort' : 'دفعة الخريف'),
    ).toBeInTheDocument()
    expect(
      await screen.findByDisplayValue(locale === 'en' ? 'Computer Engineering' : 'هندسة الحاسوب'),
    ).toBeInTheDocument()
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
    const otherLocale = locale === 'en' ? 'ar' : 'en'
    await act(async () => {
      await i18n.changeLanguage(otherLocale)
    })
    expect(screen.getByLabelText('URL')).toHaveTextContent(
      'admission_cycle_id=7&program_id=4&degree_level=MSc',
    )
    expect(
      await screen.findByDisplayValue(otherLocale === 'en' ? 'Autumn cohort' : 'دفعة الخريف'),
    ).toBeInTheDocument()
    const program = screen.getByRole('combobox', {
      name: otherLocale === 'en' ? 'Program' : 'البرنامج',
    })
    await user.clear(program)
    await user.type(program, 'حاسوب')
    expect(screen.getByLabelText('URL')).toHaveTextContent('program_id=4')
    await user.click(
      await screen.findByRole('option', {
        name: otherLocale === 'en' ? 'Software Engineering' : 'هندسة البرمجيات',
      }),
    )
    await vi.waitFor(() =>
      expect(
        api.calls.filter((call) => call.url?.includes('application-pipeline')).at(-1)?.params,
      ).toMatchObject({ program_id: 309 }),
    )
    await user.click(screen.getByRole('button', { name: 'Back' }))
    expect(
      await screen.findByDisplayValue(
        otherLocale === 'en' ? 'Computer Engineering' : 'هندسة الحاسوب',
      ),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('URL')).toHaveTextContent('program_id=4')
  },
)

it.each(['en', 'ar'] as const)(
  'announces the %s Pipeline skeleton without a spinner',
  async (locale) => {
    api.pending = true
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter initialEntries={['/analytics/application-pipeline?admission_cycle_id=7']}>
            <ApplicationPipelineReportPage />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    expect(
      await screen.findByRole('status', { name: i18n.t('pipeline.loading', { ns: 'admissions' }) }),
    ).toBeVisible()
    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument()
  },
)
