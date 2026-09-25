import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CapacityOverrideQueuePage } from './CapacityOverrideQueuePage'

const calls = vi.hoisted(() => [] as AxiosRequestConfig[])
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    calls.push(config)
    if (config.method === 'GET') {
      return Promise.resolve({
        data: [
          {
            id: 4,
            registration_request_id: 8,
            course_offering_id: 12,
            course_id: 3,
            department_id: 2,
            state: 'Pending',
            reason: null,
          },
        ],
      })
    }
    return Promise.resolve({ data: { id: 4, state: 'Approved', reason: 'Required course' } })
  },
}))

beforeEach(async () => {
  calls.length = 0
  await i18n.changeLanguage('en')
})

it('lets the scoped department head approve a capacity exception with a reason', async () => {
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <CapacityOverrideQueuePage />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByRole('heading', { name: 'Course #3' })).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText(/Decision reason/), {
    target: { value: 'Required course' },
  })
  fireEvent.click(screen.getByRole('button', { name: 'Approve exception' }))
  await vi.waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true))
})

it('shows the capacity queue in Arabic', async () => {
  await i18n.changeLanguage('ar')
  render(
    <ThemeProvider theme={createAppTheme('ar')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <CapacityOverrideQueuePage />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByText('استثناءات السعة')).toBeInTheDocument()
  expect(await screen.findByRole('heading', { name: 'المقرر #3' })).toBeInTheDocument()
})

it('requires confirmation before denying a capacity exception', async () => {
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <CapacityOverrideQueuePage />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByRole('heading', { name: 'Course #3' })).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText(/Decision reason/), { target: { value: 'No capacity' } })
  fireEvent.click(screen.getByRole('button', { name: 'Deny exception' }))
  expect(await screen.findByText(/registration request will be rejected/)).toBeInTheDocument()
  expect(calls.some((call) => call.method === 'POST')).toBe(false)
  const confirmations = screen.getAllByRole('button', { name: 'Deny exception' })
  fireEvent.click(confirmations[confirmations.length - 1])
  await vi.waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true))
})
