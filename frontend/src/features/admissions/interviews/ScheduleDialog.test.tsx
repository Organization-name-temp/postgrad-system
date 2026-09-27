import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ScheduleDialog } from './ScheduleDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    return Promise.resolve({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 100 },
    })
  },
}))
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
