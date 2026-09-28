import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { SchedulingPage } from './SchedulingPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/interviews'))
      return Promise.resolve({
        data: [],
        meta: { current_page: config.params?.page ?? 1, last_page: 3, total: 51, per_page: 25 },
      })
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['workbook']))
    return Promise.resolve({
      data: [
        {
          id: 7,
          name_en: 'Ahmed Applicant',
          name_ar: 'أحمد المتقدم',
          national_id: '01234567890123',
          age_days: 8,
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
            '/interviews/scheduling?awaiting_q=احمد&awaiting_program_id=4&awaiting_admission_cycle_id=2&awaiting_age_days_min=7&awaiting_sort=-age_days&awaiting_page=2&booked_q=Other',
          ]}
        >
          <SchedulingPage />
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
  expect(
    within(screen.getByRole('region', { name: 'Awaiting scheduling' })).getByLabelText(
      'Search applicants',
    ),
  ).toHaveValue('احمد')
  expect(
    api.calls.filter((call) => call.url?.endsWith('/applications/awaiting-interview')).at(-1)
      ?.params,
  ).toMatchObject({
    q: 'احمد',
    program_id: '4',
    admission_cycle_id: '2',
    age_days_min: 7,
    sort: '-age_days',
    page: 2,
  })
  fireEvent.change(
    within(screen.getByRole('region', { name: 'Awaiting scheduling' })).getByLabelText(
      'Search applicants',
    ),
    { target: { value: 'Sara' } },
  )
  await vi.waitFor(() =>
    expect(
      api.calls.filter((call) => call.url?.endsWith('/applications/awaiting-interview')).at(-1)
        ?.params,
    ).toMatchObject({ q: 'Sara', page: 1 }),
  )
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))
  await vi.waitFor(() =>
    expect(
      within(screen.getByRole('region', { name: 'Awaiting scheduling' })).getByLabelText(
        'Search applicants',
      ),
    ).toHaveValue('احمد'),
  )
  expect(screen.getByLabelText('URL')).toHaveTextContent('awaiting_page=2')
  expect(screen.getByLabelText('URL')).toHaveTextContent('booked_q=Other')
})
it('exports bookmarked criteria through the authenticated client as a blob', async () => {
  renderPage()
  await screen.findByText('Ahmed Applicant')
  fireEvent.click(
    within(screen.getByRole('region', { name: 'Awaiting scheduling' })).getByRole('button', {
      name: 'Export all filtered rows',
    }),
  )
  await vi.waitFor(() =>
    expect(download).toHaveBeenCalledWith(expect.any(Blob), 'applications.xlsx'),
  )
  expect(api.calls.find((call) => call.url?.endsWith('/export'))).toMatchObject({
    responseType: 'blob',
    params: { q: 'احمد', program_id: '4', age_days_min: 7, page: 2 },
  })
})

it('uses an independent bookmarked booked query and exports its complete filtered selection', async () => {
  renderPage()
  const region = screen.getByRole('region', { name: 'Scheduled interviews' })
  expect(within(region).getByLabelText('Search applicants')).toHaveValue('Other')
  await vi.waitFor(() =>
    expect(
      api.calls.filter((call) => call.url?.endsWith('/interviews')).at(-1)?.params,
    ).toMatchObject({ q: 'Other', page: 1 }),
  )
  fireEvent.change(within(region).getByLabelText('Search applicants'), {
    target: { value: 'Booked name' },
  })
  await vi.waitFor(() =>
    expect(
      api.calls.filter((call) => call.url?.endsWith('/interviews')).at(-1)?.params,
    ).toMatchObject({ q: 'Booked name', page: 1 }),
  )
  expect(screen.getByLabelText('URL')).toHaveTextContent('awaiting_page=2')
  fireEvent.click(await within(region).findByRole('button', { name: 'Export all filtered rows' }))
  await vi.waitFor(() => expect(download).toHaveBeenCalledWith(expect.any(Blob), 'interviews.xlsx'))
  expect(api.calls.find((call) => call.url === '/api/v1/interviews/export')).toMatchObject({
    responseType: 'blob',
    params: { q: 'Booked name', page: 1 },
  })
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))
  await vi.waitFor(() =>
    expect(within(region).getByLabelText('Search applicants')).toHaveValue('Other'),
  )
})
