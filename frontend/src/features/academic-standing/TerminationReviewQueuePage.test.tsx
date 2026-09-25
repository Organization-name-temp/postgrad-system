import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { TerminationReviewQueuePage } from './TerminationReviewQueuePage'

const calls = vi.hoisted(() => [] as AxiosRequestConfig[])
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    calls.push(config)
    if (config.method === 'GET') {
      return Promise.resolve({
        data: [
          {
            id: 7,
            student_id: 19,
            fingerprint: 'evidence-123',
            state: 'AwaitingCouncil',
            evidence: { trigger: 'Preparatory' },
          },
        ],
      })
    }
    return Promise.resolve({ data: { id: 7, state: 'Terminated' } })
  },
}))

beforeEach(async () => {
  calls.length = 0
  await i18n.changeLanguage('en')
})

it('lets the council record a reasoned termination decision', async () => {
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <TerminationReviewQueuePage stage="council" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByText('Student #19')).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText(/Assessment or reason/), {
    target: { value: 'Evidence confirmed' },
  })
  fireEvent.click(screen.getByRole('button', { name: 'Confirm termination' }))
  expect(
    await screen.findByText(/ends the student’s active academic lifecycle/),
  ).toBeInTheDocument()
  const confirmations = screen.getAllByRole('button', { name: 'Confirm termination' })
  fireEvent.click(confirmations[confirmations.length - 1])
  await vi.waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true))
}, 10_000)

it('renders the reviewer queue in Arabic without exposing translation keys', async () => {
  await i18n.changeLanguage('ar')
  render(
    <ThemeProvider theme={createAppTheme('ar')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <TerminationReviewQueuePage stage="council" />
      </QueryClientProvider>
    </ThemeProvider>,
  )

  expect(await screen.findByText('قرارات إنهاء القيد')).toBeInTheDocument()
  expect(screen.queryByText(/termination\.review/)).not.toBeInTheDocument()
})
