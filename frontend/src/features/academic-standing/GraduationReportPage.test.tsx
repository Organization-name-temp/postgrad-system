import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { AxiosRequestConfig } from 'axios'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { GraduationReportPage } from './GraduationReportPage'

vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    if (config.url?.endsWith('/reports/graduation-readiness')) {
      return Promise.resolve({
        data: {
          total: 3,
          groups: { ready: 0, not_ready: 2, unavailable: 1 },
          blockers: {
            RemainingRequirements: 2,
            CgpaUnavailable: 2,
            CgpaBelowMinimum: 0,
            TerminationPending: 0,
          },
        },
      })
    }
    return Promise.resolve({ data: [] })
  },
}))
function Location() {
  const location = useLocation()
  return <output aria-label="URL">{location.search}</output>
}

it.each(['en', 'ar'] as const)(
  'renders %s advisory groups and overlapping blocker drill criteria',
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
              '/analytics/graduation-readiness?lifecycle=Terminated&proximity=remaining_1_5&blocker=RemainingRequirements,TerminationPending&blocker_all=RemainingRequirements',
            ]}
          >
            <GraduationReportPage />
            <Location />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    await screen.findByRole('heading', {
      name: locale === 'en' ? 'Graduation Readiness Report' : 'تقرير جاهزية التخرج',
    })
    expect(
      screen.getByText(locale === 'en' ? /Historical advisory facts/ : /معلومات تاريخية استرشادية/),
    ).toBeVisible()
    expect(
      screen.queryByRole('combobox', {
        name: i18n.t('report.standing', { ns: 'academicStanding' }),
      }),
    ).not.toBeInTheDocument()
    const blockers = await screen.findByRole('region', {
      name: locale === 'en' ? 'Blockers (overlapping)' : 'العوائق (متداخلة)',
    })
    await user.click(
      within(blockers).getByRole('button', {
        name: i18n.t('collection.viewTable', { ns: 'common' }),
      }),
    )
    const row = within(blockers).getByRole('row', {
      name: locale === 'en' ? /CGPA unavailable.*2/ : /المعدل التراكمي غير متاح.*2/,
    })
    expect(within(row).getByRole('link')).toHaveAttribute(
      'href',
      '/analytics/graduation-readiness/students?lifecycle=Terminated&proximity=remaining_1_5&blocker=RemainingRequirements%2CTerminationPending&blocker_all=RemainingRequirements%2CCgpaUnavailable',
    )
    const before = screen.getByLabelText('URL').textContent
    await act(async () => {
      await i18n.changeLanguage(locale === 'en' ? 'ar' : 'en')
    })
    expect(screen.getByLabelText('URL')).toHaveTextContent(before ?? '')
  },
)
