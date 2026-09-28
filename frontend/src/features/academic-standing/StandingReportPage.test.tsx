import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { AxiosRequestConfig } from 'axios'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { StandingReportPage } from './StandingReportPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/academic-standing/reports/standing') {
      return Promise.resolve({
        data: {
          total: 3,
          counts: {
            TerminationPending: 0,
            NotYetAssessed: 1,
            AcademicProbation: 0,
            AcademicWarning: 0,
            GoodStanding: 2,
          },
        },
      })
    }
    const references: Record<string, { id: number; name_en: string; name_ar: string }[]> = {
      '/api/v1/faculties': [{ id: 7, name_en: 'Engineering', name_ar: 'الهندسة' }],
      '/api/v1/departments': [{ id: 11, name_en: 'Computing', name_ar: 'الحوسبة' }],
      '/api/v1/programs': [
        { id: 309, name_en: 'Software Engineering', name_ar: 'هندسة البرمجيات' },
      ],
    }
    return Promise.resolve({
      data: config.params?.id ? (references[config.url ?? ''] ?? []) : [],
      meta: { total: 101 },
    })
  },
}))

beforeEach(() => {
  api.calls = []
})
function Location() {
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
  'restores named %s academic criteria and drills exactly the selected Standing band',
  async (locale) => {
    await i18n.changeLanguage(locale)
    const user = userEvent.setup()
    const criteria =
      'faculty_id=7&department_id=11&program_id=309&degree_level=MSc&lifecycle=Terminated&q=Ahmed'
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter initialEntries={[`/analytics/academic-standing?${criteria}`]}>
            <StandingReportPage />
            <Location />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    await screen.findByRole('heading', {
      name: locale === 'en' ? 'Academic Standing Report' : 'تقرير الوضع الأكاديمي',
    })
    const program = await screen.findByRole('combobox', {
      name: locale === 'en' ? 'Program' : 'البرنامج',
    })
    expect(program).toHaveValue(locale === 'en' ? 'Software Engineering' : 'هندسة البرمجيات')
    await user.click(
      await screen.findByRole('button', { name: i18n.t('collection.viewTable', { ns: 'common' }) }),
    )
    const row = await screen.findByRole('row', {
      name: locale === 'en' ? /Good Standing.*2/ : /حالة أكاديمية جيدة.*2/,
    })
    const link = within(row).getByRole('link')
    expect(link).toHaveAttribute(
      'href',
      `/analytics/academic-standing/students?${criteria}&standing=GoodStanding`,
    )
    expect(within(row).getByText('2')).toBeVisible()
    expect(api.calls.find((call) => call.url?.endsWith('/reports/standing'))?.params).toMatchObject(
      {
        faculty_id: '7',
        department_id: '11',
        program_id: '309',
        degree_level: 'MSc',
        lifecycle: 'Terminated',
        q: 'Ahmed',
      },
    )
    const before = screen.getByLabelText('URL').textContent
    await act(async () => {
      await i18n.changeLanguage(locale === 'en' ? 'ar' : 'en')
    })
    expect(screen.getByLabelText('URL')).toHaveTextContent(before ?? '')
    expect(
      screen.getByRole('combobox', { name: locale === 'en' ? 'البرنامج' : 'Program' }),
    ).toHaveValue(locale === 'en' ? 'هندسة البرمجيات' : 'Software Engineering')
    await user.click(
      screen.getByRole('combobox', {
        name: i18n.t('report.lifecycle', { ns: 'academicStanding' }),
      }),
    )
    await user.click(
      screen.getByRole('option', {
        name: i18n.t('report.lifecycles.Active', { ns: 'academicStanding' }),
      }),
    )
    expect(screen.getByLabelText('URL')).toHaveTextContent('lifecycle=Active')
    await user.click(screen.getByRole('button', { name: 'Back' }))
    expect(screen.getByLabelText('URL')).toHaveTextContent('lifecycle=Terminated')
    await user.click(
      await screen.findByRole('button', { name: i18n.t('collection.viewTable', { ns: 'common' }) }),
    )
    const restoredRow = await screen.findByRole('row', {
      name: locale === 'en' ? /حالة أكاديمية جيدة.*2/ : /Good Standing.*2/,
    })
    within(restoredRow).getByRole('link').focus()
    await user.keyboard('{Enter}')
    expect(screen.getByLabelText('URL')).toHaveTextContent('standing=GoodStanding')
  },
)
