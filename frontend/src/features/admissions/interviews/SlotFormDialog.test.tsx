import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { SlotFormDialog } from './SlotFormDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    return Promise.resolve({ data: [], meta: { total: 0, per_page: 100 } })
  },
}))
it('requests a bounded eligible interviewer reference when the slot form opens', async () => {
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <SlotFormDialog open onClose={() => undefined} />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByRole('dialog', { name: 'New slot' })
  await vi.waitFor(() =>
    expect(
      api.calls.find((call) => call.url === '/api/v1/interview-slots/interviewers')?.params,
    ).toMatchObject({ per_page: 100 }),
  )
})
