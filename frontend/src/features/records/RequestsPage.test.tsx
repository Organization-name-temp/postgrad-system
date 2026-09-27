import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { ThemeProvider, createTheme } from '@mui/material'
import { expect, it, vi } from 'vitest'
import '../../shared/i18n/config'
import { RequestsPage } from './RequestsPage'

const queue = vi.hoisted(() => vi.fn())
vi.mock('../../shared/access/usePermissions', () => ({
  usePermissions: () => ({ isAdmin: false, hasRole: (role: string) => role === 'R05' }),
}))
vi.mock('../../shared/api/generated/endpoints', () => ({
  useOfficialTranscriptRequestQueueAndHistory: queue,
  useSubmitOfficialTranscriptRequest: () => ({}),
  useTranscriptServiceAvailability: () => ({}),
}))
function renderPage() {
  return render(
    <ThemeProvider theme={createTheme({ cssVariables: true })}>
      <MemoryRouter>
        <RequestsPage />
      </MemoryRouter>
    </ThemeProvider>,
  )
}
it('distinguishes an empty filtered queue from loading and retryable errors', () => {
  queue.mockReturnValue({ data: { data: [] }, isPending: false, isError: false })
  renderPage()
  expect(screen.getByText('No Transcript requests match these filters.')).toBeInTheDocument()
})
it('renders readable request status with its feature-owned shared chip tone', () => {
  queue.mockReturnValue({
    data: { data: [{ id: 1, status: 'RefundPending', fee: '125.50', delivery_method: 'digital' }] },
  })
  renderPage()
  expect(screen.getByText('Refund pending').closest('[data-tone]')).toHaveAttribute(
    'data-tone',
    'warning',
  )
  expect(screen.getByText('125.50 EGP')).toHaveAttribute('dir', 'ltr')
})

it('offers retry after a queue failure and never presents that failure as an empty list', async () => {
  const refetch = vi.fn()
  queue.mockReturnValue({ data: { data: [] }, isPending: false, isError: true, refetch })
  renderPage()
  expect(screen.queryByText('No Transcript requests match these filters.')).not.toBeInTheDocument()
  expect(screen.getByRole('alert')).toHaveTextContent('Unable to load the Transcript service.')
  const { default: userEvent } = await import('@testing-library/user-event')
  await userEvent.setup().click(screen.getByRole('button', { name: 'Retry' }))
  expect(refetch).toHaveBeenCalledOnce()
})
