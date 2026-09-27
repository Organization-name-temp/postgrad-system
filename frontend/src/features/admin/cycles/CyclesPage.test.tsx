import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CyclesPage } from './CyclesPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/me'))
      return Promise.resolve({
        data: { permissions: ['calendar.manage'], roles: [{ code: 'R05' }] },
      })
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['workbook']))
    return Promise.resolve({
      data: [
        {
          id: 7,
          name_en: 'Ahmed Applicant',
          name_ar: 'أحمد المتقدم',
          intake_term: 'Fall',
          year: 2026,
          is_active: true,
          is_open: true,
        },
      ],
      meta: { current_page: config.params?.page ?? 1, last_page: 3, total: 51, per_page: 25 },
    })
  },
}))
const download = vi.hoisted(() => vi.fn())
vi.mock('../../../shared/downloadBlob', () => ({ downloadBlob: download }))
function History() {
  const location = useLocation()
  const navigate = useNavigate()
  return (
    <>
      <output aria-label="URL">{location.search}</output>
      <button onClick={() => navigate(-1)}>Back</button>
    </>
  )
}
function renderPage() {
  return render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter
          initialEntries={['/admin/cycles?q=احمد&year_min=2025&is_open=true&sort=-year&page=2']}
        >
          <CyclesPage />
          <History />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}
beforeEach(async () => {
  api.calls = []
  download.mockClear()
  await i18n.changeLanguage('en')
})
it('restores bookmarked criteria, resets pagination on search, and restores the view with Back', async () => {
  renderPage()
  expect(await screen.findByText('Ahmed Applicant')).toBeInTheDocument()
  expect(screen.getByLabelText('Search Admission Cycles')).toHaveValue('احمد')
  expect(
    api.calls.filter((call) => call.url?.endsWith('/admission-cycles')).at(-1)?.params,
  ).toMatchObject({
    q: 'احمد',
    year_min: 2025,
    is_open: 'true',
    sort: '-year',
    page: 2,
  })
  fireEvent.change(screen.getByLabelText('Search Admission Cycles'), { target: { value: 'Sara' } })
  await vi.waitFor(() =>
    expect(
      api.calls.filter((call) => call.url?.endsWith('/admission-cycles')).at(-1)?.params,
    ).toMatchObject({ q: 'Sara', page: 1 }),
  )
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))
  await vi.waitFor(() =>
    expect(screen.getByLabelText('Search Admission Cycles')).toHaveValue('احمد'),
  )
  expect(screen.getByLabelText('URL')).toHaveTextContent('page=2')
})
it('exports bookmarked criteria through the authenticated client as a blob', async () => {
  renderPage()
  await screen.findByText('Ahmed Applicant')
  fireEvent.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
  await vi.waitFor(() =>
    expect(download).toHaveBeenCalledWith(expect.any(Blob), 'admission-cycles.xlsx'),
  )
  expect(api.calls.find((call) => call.url?.endsWith('/export'))).toMatchObject({
    responseType: 'blob',
    params: { q: 'احمد', year_min: 2025, is_open: 'true', page: 2 },
  })
})
