import { ThemeProvider, createTheme } from '@mui/material'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { expect, it, vi } from 'vitest'
import i18n from '../../shared/i18n/config'
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
    <ThemeProvider theme={createTheme({ cssVariables: true })}>
      <MemoryRouter>
        <IssuanceHistoryPage />
      </MemoryRouter>
    </ThemeProvider>,
  )
  expect(history).toHaveBeenCalledWith({ q: undefined, status: undefined, page: 1, per_page: 25 })
  expect(screen.getByText(/Revoked/).closest('[data-tone]')).toHaveAttribute('data-tone', 'error')
})

it('explains successful empty history without confusing it with loading or failure', () => {
  history.mockReturnValue({
    data: { data: [], meta: { last_page: 1 } },
    isPending: false,
    isError: false,
  })
  render(
    <ThemeProvider theme={createTheme({ cssVariables: true })}>
      <MemoryRouter>
        <IssuanceHistoryPage />
      </MemoryRouter>
    </ThemeProvider>,
  )
  expect(screen.getByText('No official copies match these filters.')).toBeInTheDocument()
})

it.each(['en', 'ar'])('provides a named issuance-history loading status in %s', async (locale) => {
  await i18n.changeLanguage(locale)
  history.mockReturnValue({ isPending: true, isError: false })
  render(
    <ThemeProvider theme={createTheme({ cssVariables: true })}>
      <MemoryRouter>
        <IssuanceHistoryPage />
      </MemoryRouter>
    </ThemeProvider>,
  )
  expect(
    screen.getByRole('status', { name: i18n.t('loading', { ns: 'records' }) }),
  ).toBeInTheDocument()
})
