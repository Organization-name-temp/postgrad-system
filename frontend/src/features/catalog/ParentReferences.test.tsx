import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { DepartmentsPage } from './departments/DepartmentsPage'
import { ProgramsPage } from './programs/ProgramsPage'
import { CoursesPage } from './courses/CoursesPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], resource: '' }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/me'))
      return Promise.resolve({ data: { permissions: ['catalog.view'], roles: [{ code: 'R02' }] } })
    const root = config.url?.endsWith('/' + api.resource)
    const matched = config.params?.id === '207'
    return Promise.resolve({
      data: [
        {
          id: root ? 7 : matched ? 207 : 1,
          code: root ? 'ROOT' : 'PARENT',
          name_en: root ? 'Visible record' : matched ? 'Owning parent' : 'First parent',
          name_ar: root ? 'السجل المعروض' : matched ? 'الجهة المالكة' : 'الجهة الأولى',
          faculty_id: 207,
          department_id: 207,
          degree_level: 'MSc',
          level: 500,
          total_ch: 3,
          is_active: true,
        },
      ],
      meta: {
        total: root || matched ? 1 : 101,
        current_page: 1,
        last_page: 1,
        per_page: root ? 25 : 100,
      },
    })
  },
}))
beforeEach(() => {
  api.calls = []
})
for (const locale of ['en', 'ar'] as const) {
  it.each([
    ['departments', DepartmentsPage, 'faculties'],
    ['programs', ProgramsPage, 'departments'],
    ['courses', CoursesPage, 'departments'],
  ] as const)(
    `resolves visible %s parent labels beyond the first page in ${locale}`,
    async (resource, Page, parent) => {
      api.resource = resource
      await i18n.changeLanguage(locale)
      render(
        <ThemeProvider theme={createAppTheme(locale)}>
          <QueryClientProvider
            client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
          >
            <MemoryRouter>
              <Page />
            </MemoryRouter>
          </QueryClientProvider>
        </ThemeProvider>,
      )
      expect(
        await screen.findByText(locale === 'ar' ? 'الجهة المالكة' : 'Owning parent'),
      ).toBeInTheDocument()
      expect(
        api.calls.find((call) => call.url?.endsWith('/' + parent) && call.params?.id === '207')
          ?.params,
      ).toMatchObject({ id: '207', per_page: 100 })
    },
  )
}
