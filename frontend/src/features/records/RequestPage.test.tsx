import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
import '../../shared/i18n/config'
import { RequestPage } from './RequestPage'

const api = vi.hoisted(() => ({ preview: vi.fn(), issue: vi.fn(), refetch: vi.fn() }))
vi.mock('../../shared/access/usePermissions', () => ({
  usePermissions: () => ({ isAdmin: false, hasRole: (role: string) => role === 'R05' }),
}))
vi.mock('../../shared/api/generated/endpoints', () => ({
  useOfficialTranscriptRequestDetails: () => ({
    data: {
      data: {
        id: 1,
        status: 'ReadyForIssuance',
        fee: '125.50',
        delivery_method: 'digital',
        is_owner: false,
      },
    },
    refetch: api.refetch,
  }),
  reviewCoherentOfficialTranscriptPreview: api.preview,
  certifyReviewedOfficialTranscript: api.issue,
}))
beforeEach(() => {
  vi.clearAllMocks()
  api.preview.mockResolvedValue({
    data: {
      review_token: 'reviewed',
      snapshot: { cgpa: null, identity: { name_en: 'Sara', name_ar: 'سارة', ready: true } },
    },
  })
})
function renderPage() {
  render(
    <MemoryRouter initialEntries={['/records/requests/1']}>
      <Routes>
        <Route path="/records/requests/:requestId" element={<RequestPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

it('requires review of both language versions and discards stale preview after refused certification', async () => {
  api.issue.mockRejectedValue(new Error('Changed'))
  renderPage()
  const user = userEvent.setup()
  expect(
    screen.queryByRole('button', { name: 'Certify this reviewed copy' }),
  ).not.toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: 'Review complete official preview' }))
  expect(await screen.findByText('Sara')).toBeInTheDocument()
  expect(screen.getByText('سارة')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Certify this reviewed copy' })).toBeDisabled()
  await user.click(screen.getByRole('checkbox'))
  await user.click(screen.getByRole('button', { name: 'Certify this reviewed copy' }))
  await waitFor(() => expect(screen.queryByText('Sara')).not.toBeInTheDocument())
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  expect(api.issue).toHaveBeenCalledWith(1, { review_token: 'reviewed' })
  await user.click(screen.getByRole('button', { name: 'Review complete official preview' }))
  expect(await screen.findByRole('checkbox')).not.toBeChecked()
})

it('shows blocked readiness without creating a certification action', async () => {
  api.preview.mockRejectedValue(new Error('Unresolved identity'))
  renderPage()
  await userEvent
    .setup()
    .click(screen.getByRole('button', { name: 'Review complete official preview' }))
  expect(await screen.findByRole('alert')).toHaveTextContent('Official readiness is blocked')
  expect(
    screen.queryByRole('button', { name: 'Certify this reviewed copy' }),
  ).not.toBeInTheDocument()
  expect(api.issue).not.toHaveBeenCalled()
})
