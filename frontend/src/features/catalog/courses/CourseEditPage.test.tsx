import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CourseEditPage } from './CourseEditPage'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url?.endsWith('/me'))
      return Promise.resolve({ data: { permissions: ['catalog.view'], roles: [{ code: 'R02' }] } })
    if (config.url === '/api/v1/courses/8')
      return Promise.resolve({
        data: {
          id: 8,
          code: 'CSE511',
          department_id: 207,
          name_en: 'Algorithms',
          name_ar: 'الخوارزميات',
          level: 500,
          lecture_ch: 3,
          tutorial_ch: 0,
          lab_ch: 0,
          total_ch: 3,
        },
      })
    if (config.url === '/api/v1/departments/207')
      return Promise.resolve({
        data: { id: 207, name_en: 'Owning Department', name_ar: 'القسم المالك' },
      })
    if (config.url === '/api/v1/departments')
      return Promise.resolve({
        data: [{ id: 1, name_en: 'First Department', name_ar: 'القسم الأول' }],
        meta: { total: 101 },
      })
    return Promise.resolve({ data: [] })
  },
}))
beforeEach(() => {
  api.calls = []
})
it.each(['en', 'ar'] as const)(
  'resolves the Course owner outside the first Department page in %s',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter initialEntries={['/catalog/courses/8']}>
            <Routes>
              <Route path="/catalog/courses/:courseId" element={<CourseEditPage />} />
            </Routes>
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    expect(
      await screen.findByText(locale === 'ar' ? /القسم المالك/ : /Owning Department/),
    ).toBeInTheDocument()
    expect(api.calls).toContainEqual(expect.objectContaining({ url: '/api/v1/departments/207' }))
  },
)
