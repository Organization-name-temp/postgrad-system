import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { FacultiesPage } from './faculties/FacultiesPage'
import { DepartmentsPage } from './departments/DepartmentsPage'
import { ProgramsPage } from './programs/ProgramsPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], resource: '' }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/export')) return Promise.resolve(new Blob(['references']))
    if (config.url?.endsWith('/me'))
      return Promise.resolve({ data: { permissions: ['catalog.view'], roles: [{ code: 'R02' }] } })
    const root = config.url?.endsWith('/' + api.resource)
    return Promise.resolve({
      data: [
        {
          id: 7,
          code: root ? 'ROOT001' : 'PARENT',
          name_en: root ? 'Reference entity' : 'Parent entity',
          name_ar: 'كيان مرجعي',
          faculty_id: 7,
          department_id: 7,
          degree_level: 'MSc',
          is_active: true,
        },
      ],
      meta: { current_page: config.params?.page ?? 1, last_page: 2, total: 26, per_page: 25 },
    })
  },
}))
const download = vi.hoisted(() => vi.fn())
vi.mock('../../shared/downloadBlob', () => ({ downloadBlob: download }))
beforeEach(async () => {
  api.calls = []
  download.mockClear()
  await i18n.changeLanguage('en')
})
it.each([
  ['faculties', FacultiesPage],
  ['departments', DepartmentsPage],
  ['programs', ProgramsPage],
] as const)(
  'restores %s criteria and exports the same selection through the private client',
  async (resource, Page) => {
    api.resource = resource
    render(
      <ThemeProvider theme={createAppTheme('en')}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter initialEntries={[`/catalog/${resource}?q=ROOT&is_active=true&page=2`]}>
            <Page />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    await screen.findByText('ROOT001')
    expect(screen.getByLabelText('Search by code or name')).toHaveValue('ROOT')
    expect(api.calls.find((call) => call.url?.endsWith('/' + resource))?.params).toMatchObject({
      q: 'ROOT',
      is_active: 'true',
      page: 2,
      per_page: 25,
    })
    fireEvent.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
    await vi.waitFor(() =>
      expect(download).toHaveBeenCalledWith(expect.any(Blob), resource + '.xlsx'),
    )
    fireEvent.change(screen.getByLabelText('Search by code or name'), {
      target: { value: 'Reference' },
    })
    await vi.waitFor(() =>
      expect(
        api.calls.filter((call) => call.url?.endsWith('/' + resource)).at(-1)?.params,
      ).toMatchObject({ q: 'Reference', page: 1 }),
    )
  },
)
