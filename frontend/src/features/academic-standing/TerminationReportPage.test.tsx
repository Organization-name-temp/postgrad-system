import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { AxiosRequestConfig } from 'axios'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { TerminationReportPage } from './TerminationReportPage'

vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    if (config.url?.endsWith('/reports/termination'))
      return Promise.resolve({
        data: {
          case_count: 7,
          student_count: 4,
          states: {
            AwaitingAdvisor: 1,
            AwaitingDepartmentHead: 1,
            AwaitingCouncil: 1,
            Dismissed: 1,
            Terminated: 1,
            TriggerCleared: 1,
            EvidenceChanged: 1,
          },
        },
      })
    return Promise.resolve({ data: [] })
  },
}))
function Location() {
  const location = useLocation()
  return <output aria-label="URL">{location.search}</output>
}

it.each(['en', 'ar'] as const)(
  'keeps %s Case and Student counts separate and preserves history date drill criteria',
  async (locale) => {
    await i18n.changeLanguage(locale)
    const user = userEvent.setup()
    const criteria = 'history=include&created_from=2026-01-01&created_to=2026-01-31'
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter initialEntries={[`/analytics/termination?${criteria}`]}>
            <TerminationReportPage />
            <Location />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    await screen.findByRole('heading', {
      name: locale === 'en' ? 'Termination Report' : 'تقرير إنهاء القيد',
    })
    expect(
      await screen.findByText(
        locale === 'en' ? 'Cases in selection: 7' : 'عدد الحالات ضمن الاختيار: 7',
      ),
    ).toBeVisible()
    expect(
      screen.getByText(locale === 'en' ? 'Distinct Students: 4' : 'عدد الطلاب الفريدين: 4'),
    ).toBeVisible()
    expect(
      screen.getByRole('link', {
        name: locale === 'en' ? 'View matching Cases' : 'عرض الحالات المطابقة',
      }),
    ).toBeVisible()
    const chart = await screen.findByRole('region', {
      name: locale === 'en' ? 'Case stages and outcomes' : 'مراحل الحالات ونتائجها',
    })
    await user.click(
      within(chart).getByRole('button', { name: i18n.t('collection.viewTable', { ns: 'common' }) }),
    )
    const row = within(chart).getByRole('row', {
      name:
        locale === 'en'
          ? /Recommended: awaiting Council decision.*1/
          : /توصية بانتظار قرار المجلس.*1/,
    })
    const link = within(row).getByRole('link')
    expect(link).toHaveAttribute(
      'href',
      `/analytics/termination/cases?${criteria}&state=AwaitingCouncil`,
    )
    const before = screen.getByLabelText('URL').textContent
    await act(async () => {
      await i18n.changeLanguage(locale === 'en' ? 'ar' : 'en')
    })
    expect(screen.getByLabelText('URL')).toHaveTextContent(before ?? '')
    expect(
      screen.queryByRole('combobox', {
        name: i18n.t('report.standing', { ns: 'academicStanding' }),
      }),
    ).not.toBeInTheDocument()
  },
)
