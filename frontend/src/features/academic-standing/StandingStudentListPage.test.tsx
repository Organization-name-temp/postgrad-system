import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import type { AxiosRequestConfig } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { StandingStudentListPage } from './StandingStudentListPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], details: false }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/me')
      return Promise.resolve({
        data: {
          permissions: api.details
            ? ['standing.monitor', 'grades.view_student']
            : ['standing.monitor'],
          roles: [{ code: 'R05' }],
        },
      })
    if (config.url === '/api/v1/academic-standing/reports/standing/students')
      return Promise.resolve({
        data: [
          {
            student_id: 42,
            university_id: '001-STUDENT',
            name_en: 'Sara Student',
            name_ar: 'سارة الطالبة',
            program: {
              id: 309,
              code: 'MSC-SE',
              name_en: 'Software Engineering',
              name_ar: 'هندسة البرمجيات',
              degree_level: 'MSc',
            },
            lifecycle: 'Terminated',
            cgpa: 3,
            standing: 'GoodStanding',
            explanation: 'requirements_met',
            unavailable: [],
          },
          {
            student_id: 43,
            university_id: '002-STUDENT',
            name_en: 'Ungraded Student',
            name_ar: 'طالب بلا درجات',
            program: null,
            lifecycle: 'Terminated',
            cgpa: null,
            standing: 'NotYetAssessed',
            explanation: 'no_finalized_cgpa_grade',
            unavailable: ['program'],
          },
        ],
        meta: { current_page: 2, per_page: 25, last_page: 5, total: 102 },
      })
    if (config.url === '/api/v1/programs')
      return Promise.resolve({
        data: [{ id: 309, name_en: 'Software Engineering', name_ar: 'هندسة البرمجيات' }],
        meta: { total: 1 },
      })
    return Promise.resolve({ data: [], meta: { total: 0 } })
  },
}))

beforeEach(() => {
  api.calls = []
  api.details = false
})

it.each(['en', 'ar'] as const)(
  'shows %s current owner values and unavailable data without zero fabrication or unauthorized detail links',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter
            initialEntries={[
              '/analytics/academic-standing/students?program_id=309&lifecycle=Terminated&page=2&sort=-cgpa',
            ]}
          >
            <StandingStudentListPage />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const first = await screen.findByRole('row', { name: /001-STUDENT/ })
    expect(within(first).getByText('3.00')).toHaveAttribute('dir', 'ltr')
    expect(
      within(first).getByText(locale === 'en' ? 'Good Standing' : 'حالة أكاديمية جيدة'),
    ).toBeVisible()
    const ungraded = await screen.findByRole('row', { name: /002-STUDENT/ })
    expect(
      within(ungraded).getByText(locale === 'en' ? 'CGPA unavailable' : 'المعدل التراكمي غير متاح'),
    ).toBeVisible()
    expect(within(ungraded).queryByText('0.00')).not.toBeInTheDocument()
    expect(
      within(ungraded).getByText(locale === 'en' ? 'Program unavailable' : 'البرنامج غير متاح'),
    ).toBeVisible()
    expect(
      screen.queryByRole('link', { name: locale === 'en' ? 'Sara Student' : 'سارة الطالبة' }),
    ).not.toBeInTheDocument()
    expect(
      api.calls.find((call) => call.url?.endsWith('/standing/students'))?.params,
    ).toMatchObject({
      program_id: '309',
      lifecycle: 'Terminated',
      page: 2,
      sort: '-cgpa',
      per_page: 25,
    })
  },
)
