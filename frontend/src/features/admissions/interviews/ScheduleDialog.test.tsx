import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ScheduleDialog } from './ScheduleDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], lookup: false }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (api.lookup && config.url === '/api/v1/interview-slots') {
      const searched = config.params?.q === 'احمد'
      return Promise.resolve({
        data: [
          {
            id: searched ? 207 : 1,
            is_open: true,
            scheduled_at: '2026-10-01T10:00:00Z',
            location: searched ? 'أحمد قاعة' : 'First room',
            interviewer: { name: 'Instructor' },
          },
        ],
        meta: { total: searched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 100 },
    })
  },
}))
beforeEach(async () => {
  api.calls = []
  api.lookup = false
  await i18n.changeLanguage('en')
})
it('requests a bounded open-only reference page from the slot owner when scheduling', async () => {
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <ScheduleDialog
          open
          onClose={() => undefined}
          applicationId={7}
          applicantName="Applicant"
        />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByText(
    'There are no open slots. Create one on the Interview slots screen first.',
  )
  expect(api.calls.find((call) => call.url === '/api/v1/interview-slots')?.params).toMatchObject({
    is_open: 'true',
    per_page: 100,
  })
})

it.each(['en', 'ar'] as const)(
  'searches beyond the first reference page and submits the selected owner slot in %s',
  async (locale) => {
    await i18n.changeLanguage(locale)
    api.lookup = true
    const closed = vi.fn()
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <ScheduleDialog open onClose={closed} applicationId={7} applicantName="Applicant" />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const picker = await screen.findByRole('combobox', {
      name: locale === 'ar' ? /موعد المقابلة/ : /Interview slot/,
    })
    fireEvent.mouseDown(picker)
    fireEvent.click(await screen.findByRole('option', { name: /First room/ }))
    fireEvent.change(picker, { target: { value: 'احمد' } })
    expect(picker).toHaveValue('احمد')
    expect(
      screen.getByRole('button', {
        name: locale === 'ar' ? 'جدولة المقابلة' : 'Schedule interview',
      }),
    ).toBeDisabled()
    fireEvent.click(await screen.findByRole('option', { name: /أحمد قاعة/ }))
    fireEvent.click(
      screen.getByRole('button', {
        name: locale === 'ar' ? 'جدولة المقابلة' : 'Schedule interview',
      }),
    )
    await vi.waitFor(() => expect(closed).toHaveBeenCalled())
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      is_open: 'true',
      per_page: 100,
    })
    expect(api.calls.find((call) => call.method === 'POST')?.data).toEqual({
      interview_slot_id: 207,
    })
  },
)
