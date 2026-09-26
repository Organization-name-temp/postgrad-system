import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { expect, it, vi } from 'vitest'
import '../../shared/i18n/config'
import { IssuanceHistoryPage } from './IssuanceHistoryPage'

const history = vi.hoisted(() => vi.fn())
vi.mock('../../shared/api/generated/endpoints', () => ({
  useScopedOfficialIssuanceHistory: history,
}))
it('omits unused filters and shows readable historical status', () => {
  history.mockReturnValue({
    data: { data: [{ id: 1, status: 'Revoked', reference: 'REF-ONE' }], meta: { last_page: 1 } },
  })
  render(
    <MemoryRouter>
      <IssuanceHistoryPage />
    </MemoryRouter>,
  )
  expect(history).toHaveBeenCalledWith({ q: undefined, status: undefined, page: 1, per_page: 25 })
  expect(screen.getByText(/Revoked/)).toBeInTheDocument()
})
