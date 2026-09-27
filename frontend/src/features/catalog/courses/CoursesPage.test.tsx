import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CoursesPage } from './CoursesPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['courses']))
    if (config.url?.endsWith('/me'))
      return Promise.resolve({ data: { permissions: ['catalog.view'], roles: [{ code: 'R02' }] } })
    if (config.url?.endsWith('/departments'))
      return Promise.resolve({
        data: [{ id: 4, code: 'CSE', name_en: 'Computer Engineering', name_ar: 'هندسة الحاسوب' }],
      })
    return Promise.resolve({
      data: [
        {
          id: 7,
          department_id: 4,
          code: 'CSE511',
          name_en: 'Algorithms',
          name_ar: 'الخوارزميات',
          level: 500,
          total_ch: 3,
          is_active: true,
        },
      ],
      meta: { current_page: config.params?.page ?? 1, last_page: 2, total: 26, per_page: 25 },
    })
  },
}))
const download = vi.hoisted(() => vi.fn())
vi.mock('../../../shared/downloadBlob', () => ({ downloadBlob: download }))
beforeEach(async () => {
  api.calls = []
  download.mockClear()
  await i18n.changeLanguage('en')
})
it('restores Course URL filters and exports the same criteria through the private API client', async () => {
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter initialEntries={['/catalog/courses?q=CSE5&level=500&total_ch_min=3&page=2']}>
          <CoursesPage />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByText('CSE511')
  expect(screen.getByLabelText('Search by code or name')).toHaveValue('CSE5')
  expect(api.calls.find((call) => call.url?.endsWith('/courses'))?.params).toMatchObject({
    q: 'CSE5',
    level: '500',
    total_ch_min: 3,
    page: 2,
    per_page: 25,
  })
  fireEvent.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
  await vi.waitFor(() => expect(download).toHaveBeenCalledWith(expect.any(Blob), 'courses.xlsx'))
  expect(api.calls.find((call) => call.url?.endsWith('/export'))).toMatchObject({
    responseType: 'blob',
    params: { q: 'CSE5', level: '500', total_ch_min: 3 },
  })
  fireEvent.change(screen.getByLabelText('Search by code or name'), {
    target: { value: 'Algorithms' },
  })
  await vi.waitFor(() =>
    expect(api.calls.filter((call) => call.url?.endsWith('/courses')).at(-1)?.params).toMatchObject(
      { q: 'Algorithms', page: 1 },
    ),
  )
})
