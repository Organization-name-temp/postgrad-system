import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { SlotsPage } from './SlotsPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['workbook']))
    return Promise.resolve({
      data: [
        {
          id: 7,
          location: 'Ahmed room',
          is_open: true,
          scheduled_at: '2026-10-01T10:00:00Z',
          interviewer: { name: 'Instructor' },
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
          initialEntries={[
            '/interviews/slots?q=احمد&is_open=true&mode=virtual&duration_minutes_min=30&sort=-scheduled_at&page=2',
          ]}
        >
          <SlotsPage />
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
  expect(await screen.findByText('Ahmed room')).toBeInTheDocument()
  expect(screen.getByLabelText('Search slot locations')).toHaveValue('احمد')
  expect(
    api.calls.filter((call) => call.url?.endsWith('/interview-slots')).at(-1)?.params,
  ).toMatchObject({
    q: 'احمد',
    is_open: 'true',
    duration_minutes_min: 30,
    mode: 'virtual',
    sort: '-scheduled_at',
    page: 2,
  })
  fireEvent.change(screen.getByLabelText('Search slot locations'), { target: { value: 'Sara' } })
  await vi.waitFor(() =>
    expect(
      api.calls.filter((call) => call.url?.endsWith('/interview-slots')).at(-1)?.params,
    ).toMatchObject({ q: 'Sara', page: 1 }),
  )
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))
  await vi.waitFor(() => expect(screen.getByLabelText('Search slot locations')).toHaveValue('احمد'))
  expect(screen.getByLabelText('URL')).toHaveTextContent('page=2')
})
it('exports bookmarked criteria through the authenticated client as a blob', async () => {
  renderPage()
  await screen.findByText('Ahmed room')
  fireEvent.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
  await vi.waitFor(() =>
    expect(download).toHaveBeenCalledWith(expect.any(Blob), 'interview-slots.xlsx'),
  )
  expect(api.calls.find((call) => call.url?.endsWith('/export'))).toMatchObject({
    responseType: 'blob',
    params: { q: 'احمد', is_open: 'true', mode: 'virtual', duration_minutes_min: 30, page: 2 },
  })
})
