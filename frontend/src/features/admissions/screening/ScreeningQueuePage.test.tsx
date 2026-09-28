import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ScreeningQueuePage } from './ScreeningQueuePage'

const api = vi.hoisted(() => ({
  calls: [] as AxiosRequestConfig[],
  status: 'under_sa_review',
  age: 8 as number | null,
}))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['workbook']))
    return Promise.resolve({
      data: [
        {
          id: 7,
          name_en: 'Ahmed Applicant',
          name_ar: 'أحمد المتقدم',
          national_id: '01234567890123',
          age_days: api.age,
          status: api.status,
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
function renderPage(
  path = '/screening?q=احمد&program_id=4&admission_cycle_id=2&age_days_min=7&sort=-age_days&page=2',
) {
  return render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter initialEntries={[path]}>
          <ScreeningQueuePage />
          <History />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
}
beforeEach(async () => {
  api.calls = []
  api.status = 'under_sa_review'
  api.age = 8
  download.mockClear()
  await i18n.changeLanguage('en')
})
it('labels a drilled stage and distinguishes unavailable status age from zero', async () => {
  api.status = 'accepted'
  api.age = null
  renderPage('/screening?status=accepted&admission_cycle_id=7')
  await screen.findByText('Ahmed Applicant')
  expect(screen.getByRole('combobox', { name: 'Application Status' })).toHaveTextContent('Accepted')
  expect(screen.getByRole('columnheader', { name: 'Application Status' })).toBeInTheDocument()
  expect(screen.getByText('Entry time unavailable')).toBeInTheDocument()
  expect(screen.queryByText('0 days')).not.toBeInTheDocument()
  expect(screen.getByText('Applications matching the selected criteria.')).toBeInTheDocument()
})
it('restores bookmarked criteria, resets pagination on search, and restores the view with Back', async () => {
  renderPage()
  expect(await screen.findByText('Ahmed Applicant')).toBeInTheDocument()
  expect(screen.getByLabelText('Search applicants')).toHaveValue('احمد')
  expect(api.calls.at(-1)?.params).toMatchObject({
    q: 'احمد',
    program_id: '4',
    admission_cycle_id: '2',
    age_days_min: 7,
    sort: '-age_days',
    page: 2,
  })
  fireEvent.change(screen.getByLabelText('Search applicants'), { target: { value: 'Sara' } })
  await vi.waitFor(() => expect(api.calls.at(-1)?.params).toMatchObject({ q: 'Sara', page: 1 }))
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))
  await vi.waitFor(() => expect(screen.getByLabelText('Search applicants')).toHaveValue('احمد'))
  expect(screen.getByLabelText('URL')).toHaveTextContent('page=2')
})
it('exports bookmarked criteria through the authenticated client as a blob', async () => {
  renderPage()
  await screen.findByText('Ahmed Applicant')
  fireEvent.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
  await vi.waitFor(() =>
    expect(download).toHaveBeenCalledWith(expect.any(Blob), 'applications.xlsx'),
  )
  expect(api.calls.find((call) => call.url?.endsWith('/export'))).toMatchObject({
    responseType: 'blob',
    params: { q: 'احمد', program_id: '4', age_days_min: 7, page: 2 },
  })
})
